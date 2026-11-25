<?php

namespace App\Http\Controllers;

use App\Models\IndexedFile;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class IndexedFileController extends Controller
{
    private const FILTERS = ['category', 'item', 'period_type', 'period_value', 'region', 'tag', 'q'];

    private const SORTS = ['date_desc', 'date_asc', 'name_asc', 'name_desc'];

    /** Landing page. */
    public function landing(Request $request)
    {
        $counts = IndexedFile::query()
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $boot = [
            'categories' => config('indexing.categories'),
            'periods' => config('indexing.periods'),
            'regions' => config('indexing.regions'),
            'extensions' => config('indexing.extensions'),
            'maxMb' => $this->uploadLimitMb(),
            'appMaxMb' => config('indexing.max_kb') / 1024,
            'counts' => $counts,
            'summary' => $this->summary(),
            'bulkLimit' => config('indexing.bulk_limit'),
            'auth' => $this->authPayload($request),
            'flash' => session('status'),
            // Built from the current request, so they also work when the app lives in a sub-folder
            // such as http://localhost/FIXS/public (an absolute "/files" would 404 there).
            'urls' => [
                'files' => route('files.index'),
                'stats' => route('files.stats'),
                'store' => route('files.store'),
                'downloadSelected' => route('files.download-selected'),
                'accounts' => route('accounts.index'),
            ],
        ];

        return view('files.index', ['boot' => $boot]);
    }

    /** JSON totals per category, used by the chart and table on the right of the landing page. */
    public function stats(): JsonResponse
    {
        return response()->json($this->summary());
    }

    /** JSON search used by the landing page. Administrators can ask for the deleted files with ?trashed=1. */
    public function index(Request $request): JsonResponse
    {
        $showDeleted = $request->boolean('trashed');

        abort_if($showDeleted && ! $request->user()?->isAdministrator(), 403, 'Only administrators can see deleted files.');

        $files = IndexedFile::query()
            ->when($showDeleted, fn ($query) => $query->onlyTrashed())
            ->filter($request->only(self::FILTERS))
            ->sortBy($this->sortFrom($request))
            ->paginate(10)
            ->through(fn (IndexedFile $file) => $file->toCard());

        return response()->json($files);
    }

    public function store(Request $request): JsonResponse
    {
        $upload = $request->file('file');

        // PHP rejected the upload itself (upload_max_filesize, partial upload, no temp dir ...): say exactly why.
        if ($upload !== null && ! $upload->isValid()) {
            throw ValidationException::withMessages(['file' => $upload->getErrorMessage()]);
        }

        $hasRegion = $this->hasRegion($request->input('category'));
        $extensions = config('indexing.extensions');

        $attributes = $request->validate($this->infoRules($request) + [
            'file' => [
                'required', 'file',
                'max:'.config('indexing.max_kb'),
                // Whitelist by file extension. The "mimes" rule sniffs the content, which wrongly rejects
                // legacy .xls/.doc/.ppt and some .xlsx/.csv files on many servers.
                function (string $attribute, mixed $value, Closure $fail) use ($extensions) {
                    $extension = strtolower($value->getClientOriginalExtension());

                    if (! in_array($extension, $extensions, true)) {
                        $fail('Files of type ".'.$extension.'" are not allowed. Use: '.implode(', ', $extensions).'.');
                    }
                },
            ],
        ]);

        $extension = strtolower($upload->getClientOriginalExtension());
        $size = $upload->getSize();
        $mime = $upload->getClientMimeType();

        $path = $upload->storeAs(
            "indexing/{$attributes['category']}",
            Str::uuid().'.'.$extension,
            config('indexing.disk'),
        );

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'The file could not be saved. Check that storage/app/public is writable.',
            ]);
        }

        $file = IndexedFile::create([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'category' => $attributes['category'],
            'item' => $attributes['item'],
            'period_type' => $attributes['period_type'],
            'period_value' => $attributes['period_value'],
            'region' => $hasRegion ? ($attributes['region'] ?? null) : null,
            'tags' => IndexedFile::normalizeTags($attributes['tags'] ?? []),
            'original_name' => $upload->getClientOriginalName(),
            'path' => $path,
            'extension' => $extension,
            'mime' => $mime,
            'size' => $size,
        ]);

        return response()->json($file->toCard(), 201);
    }

    /** Edits the information about a file (name, tags, category, period ...). The stored file itself is not touched. */
    public function update(Request $request, IndexedFile $file): JsonResponse
    {
        $hasRegion = $this->hasRegion($request->input('category'));

        $attributes = $request->validate($this->infoRules($request) + [
            'file_name' => ['required', 'string', 'max:150'],
        ]);

        $fileName = $this->cleanFileName($attributes['file_name'], $file->extension);

        if ($fileName === '') {
            throw ValidationException::withMessages([
                'file_name' => 'Enter a file name without special characters such as / \\ : * ? " < > |',
            ]);
        }

        $file->update([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'category' => $attributes['category'],
            'item' => $attributes['item'],
            'period_type' => $attributes['period_type'],
            'period_value' => $attributes['period_value'],
            'region' => $hasRegion ? ($attributes['region'] ?? null) : null,
            'tags' => IndexedFile::normalizeTags($attributes['tags'] ?? []),
            'original_name' => $fileName.'.'.$file->extension,
        ]);

        return response()->json($file->toCard());
    }

    /** Brings a deleted file back. */
    public function restore(int $id): JsonResponse
    {
        $file = IndexedFile::onlyTrashed()->findOrFail($id);

        $file->showToPublic();
        $file->restore();

        return response()->json($file->toCard());
    }

    /** Validation rules for the information fields, shared by "add" and "edit". */
    private function infoRules(Request $request): array
    {
        $category = $this->categoryKey($request->input('category'));
        $periodType = $request->input('period_type');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', Rule::in(array_keys(config('indexing.categories')))],
            'item' => ['required', Rule::in(config("indexing.categories.{$category}.items", []))],
            'period_type' => ['required', Rule::in(array_keys(config('indexing.periods')))],
            'period_value' => [
                'required',
                Rule::in(collect(config('indexing.periods.'.(is_string($periodType) ? $periodType : '').'.values', []))->pluck('value')),
            ],
            'region' => [Rule::requiredIf($this->hasRegion($category)), 'nullable', Rule::in(config('indexing.regions'))],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
        ];
    }

    private function categoryKey(mixed $category): string
    {
        return is_string($category) ? $category : '';
    }

    private function hasRegion(mixed $category): bool
    {
        return (bool) config('indexing.categories.'.$this->categoryKey($category).'.has_region', false);
    }

    /** The name people see when they download the file: no path characters, and the extension is always the real one. */
    private function cleanFileName(string $name, string $extension): string
    {
        $name = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', '', $name) ?? '';
        $name = trim($name, " .\t");

        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === strtolower($extension)) {
            $name = pathinfo($name, PATHINFO_FILENAME);
        }

        return $name;
    }

    public function download(IndexedFile $file): StreamedResponse
    {
        $disk = $file->storageDisk();

        abort_if($disk === null, 404);

        return Storage::disk($disk)->download($file->path, $file->original_name);
    }

    /** One ZIP with only the files the person ticked. */
    public function downloadSelected(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.(int) config('indexing.bulk_limit')],
            'ids.*' => ['integer'],
        ]);

        $files = IndexedFile::query()
            ->whereIn('id', $data['ids'])
            ->sortBy($this->sortFrom($request))
            ->get();

        abort_if($files->isEmpty(), 404, 'None of the selected files exist any more.');

        return $this->zipResponse($files);
    }

    private function sortFrom(Request $request): string
    {
        $sort = (string) $request->input('sort', 'date_desc');

        return in_array($sort, self::SORTS, true) ? $sort : 'date_desc';
    }

    private function zipResponse($files)
    {
        abort_unless(class_exists(ZipArchive::class), 501, 'The PHP zip extension is not enabled. Enable extension=zip in php.ini and restart.');

        $tempPath = tempnam(sys_get_temp_dir(), 'agri');
        $archive = new ZipArchive();
        abort_unless($archive->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'Could not create the ZIP file.');

        $entries = [];

        foreach ($files as $file) {
            $disk = $file->storageDisk();

            if ($disk === null) {
                continue;
            }

            // category/item/original-name.ext, with the id added when two files share a name.
            $entry = $file->category.'/'.Str::slug($file->item).'/'.$file->original_name;

            if (in_array($entry, $entries, true)) {
                $entry = $file->category.'/'.Str::slug($file->item).'/'.$file->id.'_'.$file->original_name;
            }

            $entries[] = $entry;
            $archive->addFile(Storage::disk($disk)->path($file->path), $entry);
        }

        $hasEntries = count($entries) > 0;
        $archive->close();

        if (! $hasEntries) {
            @unlink($tempPath);
            abort(404, 'The selected files are missing from storage.');
        }

        return response()
            ->download($tempPath, 'agri-files-'.now()->format('Ymd-His').'.zip')
            ->deleteFileAfterSend(true);
    }

    /** Opens the file in the browser. Used when the public/storage symlink is not available. */
    public function preview(IndexedFile $file)
    {
        $disk = $file->storageDisk();

        abort_if($disk === null, 404);

        return Storage::disk($disk)->response($file->path, $file->original_name);
    }

    /** Soft delete: the record is kept and the file is taken out of public view, so an administrator can restore it. */
    public function destroy(IndexedFile $file): JsonResponse
    {
        $file->hideFromPublic();
        $file->delete();

        return response()->json(['deleted' => true]);
    }

    private function authPayload(Request $request): array
    {
        $user = $request->user();

        return [
            'role' => $user?->role ?? 'guest',
            'roleLabel' => $user?->roleLabel() ?? 'Guest',
            'name' => $user?->name,
            'isAdministrator' => (bool) $user?->isAdministrator(),
            'canDownload' => $user !== null,
        ];
    }

    /** The largest upload this server really accepts: the smaller of the app limit and the PHP limits. */
    private function uploadLimitMb(): float
    {
        $limits = array_filter([
            config('indexing.max_kb') * 1024,
            ini_parse_quantity((string) ini_get('upload_max_filesize')),
            ini_parse_quantity((string) ini_get('post_max_size')), // 0 means unlimited
        ]);

        return round(min($limits) / 1048576, 1);
    }

    /** One row per configured category, including categories that have no files yet. */
    private function summary(): array
    {
        $totals = IndexedFile::query()
            ->selectRaw('category, count(*) as files, sum(size) as bytes')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $stats = collect(config('indexing.categories'))
            ->map(function (array $category, string $key) use ($totals) {
                $total = $totals->get($key);

                return [
                    'key' => $key,
                    'label' => $category['label'],
                    'files' => (int) ($total?->files ?? 0),
                    'size' => IndexedFile::formatSize((int) ($total?->bytes ?? 0)),
                ];
            })
            ->values();

        $tags = IndexedFile::query()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatMap(fn ($list) => is_array($list) ? $list : (json_decode((string) $list, true) ?: []))
            ->countBy()
            ->sortDesc()
            ->take(12)
            ->map(fn ($count, $tag) => ['tag' => (string) $tag, 'count' => $count])
            ->values();

        return [
            'tags' => $tags,
            'stats' => $stats,
            'total_files' => $stats->sum('files'),
            'trashed_files' => auth()->user()?->isAdministrator() ? IndexedFile::onlyTrashed()->count() : 0,
            'total_size' => IndexedFile::formatSize((int) $totals->sum('bytes')),
        ];
    }
}
