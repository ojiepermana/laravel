<?php

declare(strict_types=1);

namespace OjiePermana\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Google Cloud Storage Facade.
 *
 * Proxies to the default GCS disk configured in filesystems.php.
 * All standard Laravel filesystem methods are available via this facade.
 *
 * Configuration (config/filesystems.php):
 *
 *   'disks' => [
 *       'gcs' => [
 *           'driver'          => 'gcs',
 *           'key_file'        => env('GCS_KEY_FILE'),        // Path to JSON key file
 *           'key_file_json'   => null,                        // Or JSON array directly
 *           'project_id'      => env('GCS_PROJECT_ID'),
 *           'bucket'          => env('GCS_BUCKET'),
 *           'path_prefix'     => env('GCS_PATH_PREFIX', ''),
 *           'storage_api_uri' => env('GCS_STORAGE_API_URI', 'https://storage.googleapis.com'),
 *           'visibility'      => 'public',                    // 'public' or 'private'
 *       ],
 *   ],
 *
 * Usage:
 *   GCS::put('images/photo.jpg', $fileContents);
 *   GCS::get('images/photo.jpg');
 *   GCS::url('images/photo.jpg');
 *   GCS::exists('images/photo.jpg');
 *   GCS::delete('images/photo.jpg');
 *   GCS::disk('gcs-private')->put('docs/file.pdf', $contents);
 *   GCS::signedUrl('docs/file.pdf', 3600);
 *
 * @method static bool exists(string $path)
 * @method static bool missing(string $path)
 * @method static string get(string $path)
 * @method static resource readStream(string $path)
 * @method static bool put(string $path, string|resource $contents, mixed $options = [])
 * @method static bool putFile(string $path, \Illuminate\Http\File|\Illuminate\Http\UploadedFile $file, mixed $options = [])
 * @method static string|false putFileAs(string $path, \Illuminate\Http\File|\Illuminate\Http\UploadedFile $file, string $name, mixed $options = [])
 * @method static bool writeStream(string $path, resource $resource, array $options = [])
 * @method static string url(string $path)
 * @method static string temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = [])
 * @method static string signedUrl(string $path, int|\DateTimeInterface $expiration = 3600)
 * @method static bool delete(string|array $paths)
 * @method static bool copy(string $from, string $to)
 * @method static bool move(string $from, string $to)
 * @method static int size(string $path)
 * @method static int lastModified(string $path)
 * @method static string mimeType(string $path)
 * @method static array files(string $directory = null, bool $recursive = false)
 * @method static array allFiles(string $directory = null)
 * @method static array directories(string $directory = null, bool $recursive = false)
 * @method static array allDirectories(string $directory = null)
 * @method static bool makeDirectory(string $path)
 * @method static bool deleteDirectory(string $directory)
 * @method static \Illuminate\Filesystem\FilesystemAdapter disk(string $name = 'gcs')
 * @method static \OjiePermana\Laravel\Storage\GoogleCloudStorageAdapter getAdapter()
 *
 * @see \Illuminate\Filesystem\FilesystemAdapter
 * @see \OjiePermana\Laravel\Storage\GoogleCloudStorageAdapter
 */
class GCS extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'gcs';
    }
}
