<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureStorageLink();

        // Used by the "can:administer" route middleware: only administrators may manage files and accounts.
        Gate::define('administer', fn (User $user) => $user->isAdministrator());
    }

    /**
     * Same link that `php artisan storage:link` creates (public/storage -> storage/app/public),
     * created on the fly for hosts where you cannot run artisan.
     */
    private function ensureStorageLink(): void
    {
        foreach (config('filesystems.links', []) as $link => $target) {
            // Already pointing at the target (symlink or Windows junction).
            if (realpath($link) !== false && realpath($link) === realpath($target)) {
                continue;
            }

            if (is_dir($link) && ! is_link($link)) {
                // A zip extracted on Windows turns the link into a plain folder holding only .gitignore.
                // Replace it only when nothing else is inside, so real files are never touched.
                $entries = array_diff(scandir($link), ['.', '..', '.gitignore']);

                if ($entries !== []) {
                    continue;
                }

                $this->app['files']->deleteDirectory($link);
            } elseif (file_exists($link) || is_link($link)) {
                continue;
            }

            try {
                $this->app['files']->link($target, $link);
            } catch (Throwable) {
                // No permission to link: downloads and the preview route still work without it.
            }
        }
    }
}
