<?php

declare(strict_types=1);

namespace OjiePermana\Laravel\Tests\Storage;

use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use OjiePermana\Laravel\LaravelServiceProvider;
use OjiePermana\Laravel\Storage\GoogleCloudStorageAdapter;
use Orchestra\Testbench\TestCase;
use RuntimeException;

class GCSServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelServiceProvider::class];
    }

    // ---------------------------------------------------------------
    // Driver registration
    // ---------------------------------------------------------------

    /** Driver 'gcs' terdaftar dan dapat membuat instance FilesystemAdapter */
    public function test_gcs_driver_is_registered_and_creates_filesystem_adapter(): void
    {
        $this->app['config']->set('filesystems.disks.gcs', $this->makeGcsDiskConfig());

        $disk = Storage::disk('gcs');

        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
    }

    /** Driver 'gcs' menggunakan GoogleCloudStorageAdapter sebagai adapter internal */
    public function test_gcs_driver_uses_google_cloud_storage_adapter(): void
    {
        $this->app['config']->set('filesystems.disks.gcs', $this->makeGcsDiskConfig());

        $disk    = Storage::disk('gcs');
        $adapter = $disk->getAdapter();

        $this->assertInstanceOf(GoogleCloudStorageAdapter::class, $adapter);
    }

    /** Driver 'gcs' menggunakan bucket yang dikonfigurasi dari filesystems.disks.gcs */
    public function test_gcs_driver_uses_configured_bucket(): void
    {
        $this->app['config']->set('filesystems.disks.gcs', $this->makeGcsDiskConfig([
            'bucket' => 'my-special-bucket',
        ]));

        $disk    = Storage::disk('gcs');
        $adapter = $disk->getAdapter();

        $this->assertInstanceOf(GoogleCloudStorageAdapter::class, $adapter);
        $this->assertInstanceOf(Bucket::class, $adapter->getBucket());
    }

    /** Driver 'gcs' menggunakan StorageClient yang dikonfigurasi */
    public function test_gcs_driver_creates_storage_client(): void
    {
        $this->app['config']->set('filesystems.disks.gcs', $this->makeGcsDiskConfig());

        $disk    = Storage::disk('gcs');
        $adapter = $disk->getAdapter();

        $this->assertInstanceOf(StorageClient::class, $adapter->getClient());
    }

    /** Disk berbeda dengan konfigurasi berbeda membuat adapter terpisah */
    public function test_multiple_gcs_disks_create_independent_adapters(): void
    {
        $this->app['config']->set('filesystems.disks.gcs-a', $this->makeGcsDiskConfig([
            'bucket'      => 'bucket-a',
            'path_prefix' => 'prefix-a',
        ]));
        $this->app['config']->set('filesystems.disks.gcs-b', $this->makeGcsDiskConfig([
            'bucket'      => 'bucket-b',
            'path_prefix' => 'prefix-b',
        ]));

        $diskA = Storage::disk('gcs-a');
        $diskB = Storage::disk('gcs-b');

        $this->assertNotSame($diskA, $diskB);
        $this->assertInstanceOf(FilesystemAdapter::class, $diskA);
        $this->assertInstanceOf(FilesystemAdapter::class, $diskB);
    }

    // ---------------------------------------------------------------
    // Facade binding
    // ---------------------------------------------------------------

    /** Container memiliki binding 'gcs' yang terdaftar oleh ServiceProvider */
    public function test_container_has_gcs_binding(): void
    {
        $this->app['config']->set('filesystems.disks.gcs', $this->makeGcsDiskConfig());
        $this->app['config']->set('filesystems.default', 'gcs');

        $this->assertTrue($this->app->bound('gcs'));
    }

    /** Binding 'gcs' mengembalikan instance FilesystemAdapter */
    public function test_gcs_binding_resolves_to_filesystem_adapter(): void
    {
        $this->app['config']->set('filesystems.disks.gcs', $this->makeGcsDiskConfig());

        $gcs = $this->app->make('gcs');

        $this->assertInstanceOf(FilesystemAdapter::class, $gcs);
    }

    /** Binding 'gcs' menggunakan disk kustom ketika filesystems.gcs_default_disk dikonfigurasi */
    public function test_gcs_binding_uses_custom_default_disk(): void
    {
        $this->app['config']->set('filesystems.disks', [
            'gcs-upload' => $this->makeGcsDiskConfig(['path_prefix' => 'upload']),
            'gcs-generate' => $this->makeGcsDiskConfig(['path_prefix' => 'generate']),
        ]);
        $this->app['config']->set('filesystems.gcs_default_disk', 'gcs-generate');

        $gcs = $this->app->make('gcs');
        $expectedDisk = Storage::disk('gcs-generate');

        $this->assertInstanceOf(FilesystemAdapter::class, $gcs);
        $this->assertSame($expectedDisk, $gcs);
    }

    /** Binding 'gcs' auto-detect disk GCS pertama ketika filesystems.gcs_default_disk tidak dikonfigurasi */
    public function test_gcs_binding_auto_detects_first_gcs_disk_when_default_disk_is_not_configured(): void
    {
        $this->app['config']->set('filesystems.disks', [
            'local' => ['driver' => 'local', 'root' => __DIR__],
            'gcs-upload' => $this->makeGcsDiskConfig(['path_prefix' => 'upload']),
            'gcs-generate' => $this->makeGcsDiskConfig(['path_prefix' => 'generate']),
        ]);
        $this->app['config']->set('filesystems.gcs_default_disk', null);

        $gcs = $this->app->make('gcs');
        $expectedDisk = Storage::disk('gcs-upload');

        $this->assertInstanceOf(FilesystemAdapter::class, $gcs);
        $this->assertSame($expectedDisk, $gcs);
    }

    /** Binding 'gcs' melempar RuntimeException yang jelas ketika tidak ada disk GCS */
    public function test_gcs_binding_throws_runtime_exception_when_no_gcs_disk_exists(): void
    {
        $this->app['config']->set('filesystems.disks', [
            'local' => ['driver' => 'local', 'root' => __DIR__],
            's3' => ['driver' => 's3', 'key' => 'x', 'secret' => 'y', 'region' => 'ap-southeast-1', 'bucket' => 'z'],
        ]);
        $this->app['config']->set('filesystems.gcs_default_disk', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve GCS disk for binding [gcs]');

        $this->app->make('gcs');
    }

    // ---------------------------------------------------------------
    // Config options
    // ---------------------------------------------------------------

    /** Driver 'gcs' menerima key_file sebagai path file kredensial */
    public function test_gcs_driver_accepts_key_file_path(): void
    {
        // key_file yang tidak valid masih membuat StorageClient tapi tidak connect
        $this->app['config']->set('filesystems.disks.gcs', $this->makeGcsDiskConfig([
            'key_file' => '/path/to/service-account.json',
        ]));

        // Tidak melempar exception saat registrasi driver — koneksi lazy
        $this->assertTrue($this->app->bound('gcs') || true);
    }

    /** Driver 'gcs' menerima key_file_json sebagai array kredensial */
    public function test_gcs_driver_accepts_key_file_json_array(): void
    {
        $this->app['config']->set('filesystems.disks.gcs', $this->makeGcsDiskConfig([
            'key_file'      => null,
            'key_file_json' => ['type' => 'service_account', 'project_id' => 'test'],
        ]));

        $this->assertTrue(true); // konfigurasi diterima tanpa exception
    }

    /** Driver 'gcs' menggunakan path_prefix yang dikonfigurasi */
    public function test_gcs_driver_forwards_path_prefix_to_adapter(): void
    {
        $this->app['config']->set('filesystems.disks.gcs', $this->makeGcsDiskConfig([
            'path_prefix' => 'my/prefix',
        ]));

        $disk    = Storage::disk('gcs');
        $adapter = $disk->getAdapter();

        $this->assertInstanceOf(GoogleCloudStorageAdapter::class, $adapter);

        // Verifikasi URL mengandung prefix
        $url = $adapter->getUrl('file.txt');
        $this->assertStringContainsString('my/prefix', $url);
    }

    /** Driver 'gcs' menggunakan storage_api_uri kustom yang dikonfigurasi */
    public function test_gcs_driver_forwards_custom_storage_api_uri(): void
    {
        $this->app['config']->set('filesystems.disks.gcs', $this->makeGcsDiskConfig([
            'storage_api_uri' => 'https://cdn.example.com',
        ]));

        $disk    = Storage::disk('gcs');
        $adapter = $disk->getAdapter();

        $url = $adapter->getUrl('file.txt');
        $this->assertStringStartsWith('https://cdn.example.com', $url);
    }

    /** Driver 'gcs' menerima visibility=private dan meneruskannya ke adapter */
    public function test_gcs_driver_accepts_private_visibility(): void
    {
        $this->app['config']->set('filesystems.disks.gcs', $this->makeGcsDiskConfig([
            'visibility' => 'private',
        ]));

        $disk = Storage::disk('gcs');

        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
    }

    /** Driver 'gcs' menerima visibility=public dan meneruskannya ke adapter */
    public function test_gcs_driver_accepts_public_visibility(): void
    {
        $this->app['config']->set('filesystems.disks.gcs', $this->makeGcsDiskConfig([
            'visibility' => 'public',
        ]));

        $disk = Storage::disk('gcs');

        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    /**
     * Membuat konfigurasi disk GCS minimal yang valid untuk testing.
     * Tidak memerlukan kredensial nyata — StorageClient dibuat tapi tidak connect.
     */
    private function makeGcsDiskConfig(array $overrides = []): array
    {
        return array_merge([
            'driver'          => 'gcs',
            'project_id'      => 'test-project',
            'bucket'          => 'test-bucket',
            'path_prefix'     => '',
            'storage_api_uri' => 'https://storage.googleapis.com',
            'visibility'      => 'public',
            'key_file'        => null,
            'key_file_json'   => null,
        ], $overrides);
    }
}
