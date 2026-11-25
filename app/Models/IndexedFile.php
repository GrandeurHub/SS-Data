<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class IndexedFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'description', 'category', 'item', 'period_type', 'period_value',
        'region', 'tags', 'original_name', 'path', 'extension', 'mime', 'size',
    ];

    /** Keywords are stored as a JSON list (unicode kept readable so they can be searched with LIKE). */
    protected function tags(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? (json_decode($value, true) ?: []) : [],
            set: fn (?array $value) => json_encode(array_values($value ?? []), JSON_UNESCAPED_UNICODE),
        );
    }

    public static function normalizeTag(string $tag): string
    {
        $tag = mb_strtolower(trim($tag));
        $tag = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $tag) ?? '';
        $tag = trim(preg_replace('/\s+/u', ' ', $tag) ?? '');

        return mb_substr($tag, 0, 30);
    }

    /** Lower-cased, de-duplicated, at most ten keywords. */
    public static function normalizeTags(array $tags): array
    {
        return collect($tags)
            ->map(fn ($tag) => static::normalizeTag((string) $tag))
            ->filter()
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }

    public function scopeFilter(Builder $builder, array $filters): Builder
    {
        return $builder
            ->when($filters['category'] ?? null, fn ($query, $value) => $query->where('category', $value))
            ->when($filters['item'] ?? null, fn ($query, $value) => $query->where('item', $value))
            ->when($filters['period_type'] ?? null, fn ($query, $value) => $query->where('period_type', $value))
            ->when($filters['period_value'] ?? null, fn ($query, $value) => $query->where('period_value', $value))
            ->when($filters['region'] ?? null, fn ($query, $value) => $query->where('region', $value))
            ->when($filters['tag'] ?? null, fn ($query, $value) => $query->where('tags', 'like', '%"'.static::normalizeTag($value).'"%'))
            ->when($filters['q'] ?? null, function ($query, $value) {
                $query->where(function ($inner) use ($value) {
                    $inner->where('title', 'like', "%{$value}%")
                        ->orWhere('description', 'like', "%{$value}%")
                        ->orWhere('original_name', 'like', "%{$value}%")
                        ->orWhere('tags', 'like', "%{$value}%");
                });
            });
    }

    /** Sorts by name (title) or by date added; the id keeps the order stable when values tie. */
    public function scopeSortBy(Builder $builder, string $sort): Builder
    {
        return match ($sort) {
            'name_asc' => $builder->orderByRaw('lower(title) asc')->orderBy('id'),
            'name_desc' => $builder->orderByRaw('lower(title) desc')->orderByDesc('id'),
            'date_asc' => $builder->orderBy('created_at')->orderBy('id'),
            default => $builder->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    public function periodLabel(): string
    {
        $period = config("indexing.periods.{$this->period_type}");
        $value = collect($period['values'] ?? [])->firstWhere('value', $this->period_value);

        return ($period['label'] ?? $this->period_type).': '.($value['label'] ?? $this->period_value);
    }

    public function sizeLabel(): string
    {
        return static::formatSize((int) $this->size);
    }

    public static function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $amount = (float) $bytes;
        $step = 0;

        while ($amount >= 1024 && $step < count($units) - 1) {
            $amount /= 1024;
            $step++;
        }

        return round($amount, $step ? 1 : 0).' '.$units[$step];
    }

    /** The disk that really holds this file: the configured one, or "local" for files uploaded before the symlink change. */
    public function storageDisk(): ?string
    {
        foreach ([config('indexing.disk'), 'local'] as $disk) {
            if (Storage::disk($disk)->exists($this->path)) {
                return $disk;
            }
        }

        return null;
    }

    /** Direct URL through the public/storage symlink; falls back to the preview route when the link is missing. */
    public function publicUrl(): string
    {
        return is_file(public_path('storage/'.$this->path))
            ? asset('storage/'.$this->path)
            : route('files.preview', $this);
    }

    /**
     * A deleted file leaves the public/storage folder (so its direct link stops working) and waits on the
     * private "local" disk. storageDisk() already looks there, so restoring only has to move it back.
     */
    public function hideFromPublic(): void
    {
        $this->moveBetweenDisks(config('indexing.disk'), 'local');
    }

    public function showToPublic(): void
    {
        $this->moveBetweenDisks('local', config('indexing.disk'));
    }

    private function moveBetweenDisks(string $from, string $to): void
    {
        if ($from === $to || ! Storage::disk($from)->exists($this->path)) {
            return;
        }

        try {
            if (! Storage::disk($to)->exists($this->path)) {
                Storage::disk($to)->writeStream($this->path, Storage::disk($from)->readStream($this->path));
            }

            Storage::disk($from)->delete($this->path);
        } catch (\Throwable $exception) {
            report($exception); // The record is still deleted or restored; the file just stays where it was.
        }
    }

    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'file_name' => pathinfo($this->original_name, PATHINFO_FILENAME),
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'category_label' => config("indexing.categories.{$this->category}.label"),
            'item' => $this->item,
            'period' => $this->periodLabel(),
            'period_type' => $this->period_type,
            'period_value' => $this->period_value,
            'region' => $this->region,
            'tags' => $this->tags,
            'extension' => $this->extension,
            'size' => $this->sizeLabel(),
            'added' => $this->created_at->format('M j, Y'),
            'deleted' => $this->deleted_at?->format('M j, Y'),
            'download_url' => route('files.download', $this),
            'view_url' => $this->publicUrl(),
        ];
    }
}
