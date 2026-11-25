# Agri File Index (Laravel 11/12)

Copy these files into a fresh Laravel project (same paths), then:

    php artisan migrate
    php artisan storage:link
    php artisan serve

Open http://localhost:8000

Uploads are stored on the `public` disk (storage/app/public/indexing/<category>/) and exposed
through the public/storage symlink, so each file has a direct URL (Open) plus a download route
that keeps the original file name (Download). If the link is missing, AppServiceProvider
creates it on boot. Override the disk with INDEXING_DISK in .env. Files uploaded before this
change stay in storage/app/private and still download and delete normally.

Allowed types and the size limit live in config/indexing.php.
