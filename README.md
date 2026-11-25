# Agri File Index (Laravel 11/12)

Copy these files into your Laravel project (same paths), then:

    php artisan optimize:clear
    php artisan migrate        # adds the new "tags" column
    php artisan storage:link
    php artisan serve

Open the address that `php artisan serve` prints (or your XAMPP/Laragon address, e.g. http://localhost/FIXS/public).
The page builds all its request URLs from the address it is opened at, so it works in a sub-folder too.

Logo: save the PhilRice logo as public/images/philrice-logo.png (any size, it is shown at 36x36 px).
Until the file exists the page shows a placeholder icon.

Tags: add keywords when uploading; they are searched together with the title and file name,
and can be clicked on a file or in "Popular tags" to filter.

Download all: when a search has results, "Download N files (.zip)" packs the matching files
(newest 200, see bulk_limit in config/indexing.php). Needs the PHP zip extension (enable extension=zip).

Uploads live on the `public` disk (storage/app/public/indexing/<category>/) behind the public/storage symlink.
If `storage:link` says the link exists, delete the plain folder public/storage and run it again.
Upload errors show in the dialog. Raise `upload_max_filesize` / `post_max_size` in php.ini for big files.

## Login, roles and deleted items

Three kinds of visitors:

| Who | Can do |
| --- | --- |
| Guest (not logged in) | View the file list and open files in the browser. No downloads. |
| Client | Everything a guest can, plus download (single files and "Download selected" ZIP). |
| Administrator | Everything a client can, plus add, edit and delete files, restore deleted files, and create, edit, delete and restore accounts. |

Username and Password boxes are in the upper-right corner of the page (on small screens, tap "Log in").

Set up (after copying the files):

    php artisan optimize:clear
    php artisan migrate        # adds username, role and "deleted" columns
    php artisan db:seed        # creates the first administrator

The first administrator is username `admin`, password `ChangeMe!2026` (or the value of ADMIN_PASSWORD in .env
when you seed). Log in, open Accounts, and change that password straight away. Then create the client accounts there.

Deleting never destroys data: deleted files and accounts go to "Deleted files" (button above the list) and the
"Deleted" tab of Accounts, where an administrator can restore them. A deleted file is moved out of
storage/app/public, so its direct /storage link stops working until it is restored. Deleted accounts cannot log in.

Safety rules: an administrator cannot delete their own account or change their own role, so there is always one administrator left.

Tests (optional): `php artisan test` runs tests/Feature/AccessControlTest.php, which checks the permission rules above.
