<?php

declare(strict_types=1);

namespace OjiePermana\Laravel\Tests\Storage;

use DateTimeImmutable;
use DateTimeInterface;
use Google\Cloud\Storage\Acl;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\StorageObject;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use OjiePermana\Laravel\Storage\GoogleCloudStorageAdapter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

#[AllowMockObjectsWithoutExpectations]
class GoogleCloudStorageAdapterTest extends TestCase
{
    private const BUCKET      = 'test-bucket';
    private const PREFIX      = 'uploads';
    private const STORAGE_URI = 'https://storage.googleapis.com';

    private StorageClient&MockObject $client;
    private Bucket&MockObject        $bucket;

    protected function setUp(): void
    {
        $this->bucket = $this->createMock(Bucket::class);
        $this->client = $this->createMock(StorageClient::class);
        $this->client->method('bucket')->with(self::BUCKET)->willReturn($this->bucket);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function makeAdapter(string $prefix = self::PREFIX): GoogleCloudStorageAdapter
    {
        return new GoogleCloudStorageAdapter(
            client:            $this->client,
            bucketName:        self::BUCKET,
            pathPrefix:        $prefix,
            storageApiUri:     self::STORAGE_URI,
            defaultVisibility: Visibility::PUBLIC,
        );
    }

    /**
     * Membuat stub StorageObject (hanya return values, tanpa expectations).
     * Gunakan createMock() langsung di test jika perlu expects().
     */
    private function makeObject(bool $exists = true, array $info = []): StorageObject&MockObject
    {
        $stub = $this->createMock(StorageObject::class);
        $stub->method('exists')->willReturn($exists);
        $stub->method('info')->willReturn($info);

        return $stub;
    }

    /**
     * Membuat iterator yang mensimulasikan hasil bucket->objects().
     * Mendukung metode prefixes() untuk non-deep listing.
     */
    private function makeObjectList(array $objects, array $prefixes = []): object
    {
        return new class($objects, $prefixes) implements \Iterator {
            private int $position = 0;

            public function __construct(
                private array $items,
                private array $directoryPrefixes,
            ) {}

            public function prefixes(): array { return $this->directoryPrefixes; }
            public function current(): mixed  { return $this->items[$this->position]; }
            public function key(): int        { return $this->position; }
            public function next(): void      { $this->position++; }
            public function rewind(): void    { $this->position = 0; }
            public function valid(): bool     { return isset($this->items[$this->position]); }
        };
    }

    // ---------------------------------------------------------------
    // fileExists
    // ---------------------------------------------------------------

    /** fileExists mengembalikan true ketika objek GCS ada */
    public function test_fileExists_returns_true_when_object_exists(): void
    {
        $stub = $this->makeObject(exists: true);
        $this->bucket->method('object')->with('uploads/photo.jpg')->willReturn($stub);

        $this->assertTrue($this->makeAdapter()->fileExists('photo.jpg'));
    }

    /** fileExists mengembalikan false ketika objek GCS tidak ada */
    public function test_fileExists_returns_false_when_object_missing(): void
    {
        $stub = $this->makeObject(exists: false);
        $this->bucket->method('object')->with('uploads/photo.jpg')->willReturn($stub);

        $this->assertFalse($this->makeAdapter()->fileExists('photo.jpg'));
    }

    /** fileExists tanpa prefix menggunakan path mentah langsung */
    public function test_fileExists_without_prefix_uses_raw_path(): void
    {
        $stub = $this->makeObject(exists: true);
        $this->bucket->method('object')->with('photo.jpg')->willReturn($stub);

        $this->assertTrue($this->makeAdapter('')->fileExists('photo.jpg'));
    }

    /** fileExists menghapus slash di awal path sebelum memanggil GCS */
    public function test_fileExists_strips_leading_slash_from_path(): void
    {
        $stub = $this->makeObject(exists: true);
        $this->bucket->method('object')->with('uploads/file.txt')->willReturn($stub);

        $this->assertTrue($this->makeAdapter()->fileExists('/file.txt'));
    }

    // ---------------------------------------------------------------
    // directoryExists
    // ---------------------------------------------------------------

    /** directoryExists mengembalikan true ketika direktori memiliki objek */
    public function test_directoryExists_returns_true_when_objects_found(): void
    {
        $this->bucket->method('objects')
            ->with(['prefix' => 'uploads/images/', 'maxResults' => 1])
            ->willReturn($this->makeObjectList([$this->makeObject()]));

        $this->assertTrue($this->makeAdapter()->directoryExists('images'));
    }

    /** directoryExists mengembalikan false ketika direktori kosong */
    public function test_directoryExists_returns_false_when_no_objects(): void
    {
        $this->bucket->method('objects')
            ->with(['prefix' => 'uploads/images/', 'maxResults' => 1])
            ->willReturn($this->makeObjectList([]));

        $this->assertFalse($this->makeAdapter()->directoryExists('images'));
    }

    /** directoryExists tanpa prefix menggunakan path direktori langsung */
    public function test_directoryExists_without_prefix(): void
    {
        $this->bucket->method('objects')
            ->with(['prefix' => 'docs/', 'maxResults' => 1])
            ->willReturn($this->makeObjectList([$this->makeObject()]));

        $this->assertTrue($this->makeAdapter('')->directoryExists('docs'));
    }

    // ---------------------------------------------------------------
    // write
    // ---------------------------------------------------------------

    /** write mengunggah konten dengan predefinedAcl=publicRead untuk visibility public */
    public function test_write_uploads_with_public_acl(): void
    {
        $this->bucket->expects($this->once())
            ->method('upload')
            ->with('hello world', $this->callback(function (array $opts): bool {
                return $opts['name'] === 'uploads/file.txt'
                    && $opts['predefinedAcl'] === 'publicRead';
            }))
            ->willReturn($this->makeObject());

        $this->makeAdapter()->write('file.txt', 'hello world', new Config([
            Config::OPTION_VISIBILITY => Visibility::PUBLIC,
        ]));
    }

    /** write tidak menyertakan predefinedAcl=publicRead untuk visibility private */
    public function test_write_uploads_without_public_acl_when_private(): void
    {
        $this->bucket->expects($this->once())
            ->method('upload')
            ->with('secret', $this->callback(function (array $opts): bool {
                return $opts['name'] === 'uploads/file.txt'
                    && (! isset($opts['predefinedAcl']) || $opts['predefinedAcl'] !== 'publicRead');
            }))
            ->willReturn($this->makeObject());

        $this->makeAdapter()->write('file.txt', 'secret', new Config([
            Config::OPTION_VISIBILITY => Visibility::PRIVATE,
        ]));
    }

    /** write menggunakan default visibility (public) ketika Config tidak menyertakan visibility */
    public function test_write_uses_default_public_visibility_when_config_empty(): void
    {
        $this->bucket->expects($this->once())
            ->method('upload')
            ->with('data', $this->callback(function (array $opts): bool {
                return $opts['predefinedAcl'] === 'publicRead';
            }))
            ->willReturn($this->makeObject());

        $this->makeAdapter()->write('file.txt', 'data', new Config());
    }

    /** write menyertakan contentType ketika 'mimetype' ada di Config */
    public function test_write_includes_content_type_from_config(): void
    {
        $this->bucket->expects($this->once())
            ->method('upload')
            ->with('img', $this->callback(function (array $opts): bool {
                return isset($opts['metadata']['contentType'])
                    && $opts['metadata']['contentType'] === 'image/jpeg';
            }))
            ->willReturn($this->makeObject());

        $this->makeAdapter()->write('photo.jpg', 'img', new Config([
            'mimetype' => 'image/jpeg',
        ]));
    }

    /** write membangun path dengan prefix yang benar */
    public function test_write_builds_correct_path_with_prefix(): void
    {
        $this->bucket->expects($this->once())
            ->method('upload')
            ->with('data', $this->callback(function (array $opts): bool {
                return $opts['name'] === 'uploads/sub/file.txt';
            }))
            ->willReturn($this->makeObject());

        $this->makeAdapter()->write('sub/file.txt', 'data', new Config());
    }

    /** write melempar UnableToWriteFile ketika GCS melempar exception */
    public function test_write_throws_UnableToWriteFile_on_exception(): void
    {
        $this->bucket->method('upload')
            ->willThrowException(new \RuntimeException('upload failed'));

        $this->expectException(UnableToWriteFile::class);
        $this->makeAdapter()->write('file.txt', 'data', new Config());
    }

    // ---------------------------------------------------------------
    // writeStream
    // ---------------------------------------------------------------

    /** writeStream mengunggah PHP resource stream ke GCS */
    public function test_writeStream_uploads_resource_stream(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'stream contents');
        rewind($resource);

        $this->bucket->expects($this->once())
            ->method('upload')
            ->with($resource, $this->callback(fn (array $opts): bool => $opts['name'] === 'uploads/file.txt'))
            ->willReturn($this->makeObject());

        $this->makeAdapter()->writeStream('file.txt', $resource, new Config());

        fclose($resource);
    }

    /** writeStream menghormati visibility dari Config */
    public function test_writeStream_respects_visibility_config(): void
    {
        $resource = fopen('php://temp', 'r+');

        $this->bucket->expects($this->once())
            ->method('upload')
            ->with($resource, $this->callback(function (array $opts): bool {
                return ! isset($opts['predefinedAcl']) || $opts['predefinedAcl'] !== 'publicRead';
            }))
            ->willReturn($this->makeObject());

        $this->makeAdapter()->writeStream('file.txt', $resource, new Config([
            Config::OPTION_VISIBILITY => Visibility::PRIVATE,
        ]));

        fclose($resource);
    }

    /** writeStream melempar UnableToWriteFile ketika GCS melempar exception */
    public function test_writeStream_throws_UnableToWriteFile_on_exception(): void
    {
        $resource = fopen('php://temp', 'r+');
        $this->bucket->method('upload')
            ->willThrowException(new \RuntimeException('network error'));

        $this->expectException(UnableToWriteFile::class);

        try {
            $this->makeAdapter()->writeStream('file.txt', $resource, new Config());
        } finally {
            fclose($resource);
        }
    }

    // ---------------------------------------------------------------
    // read
    // ---------------------------------------------------------------

    /** read mengembalikan isi file sebagai string */
    public function test_read_returns_file_contents(): void
    {
        $stub = $this->makeObject(exists: true);
        $stub->method('downloadAsString')->willReturn('file content here');
        $this->bucket->method('object')->with('uploads/readme.txt')->willReturn($stub);

        $this->assertSame('file content here', $this->makeAdapter()->read('readme.txt'));
    }

    /** read mengembalikan string kosong ketika file kosong */
    public function test_read_returns_empty_string_for_empty_file(): void
    {
        $stub = $this->makeObject(exists: true);
        $stub->method('downloadAsString')->willReturn('');
        $this->bucket->method('object')->willReturn($stub);

        $this->assertSame('', $this->makeAdapter()->read('empty.txt'));
    }

    /** read melempar UnableToReadFile ketika file tidak ada */
    public function test_read_throws_UnableToReadFile_when_file_missing(): void
    {
        $stub = $this->makeObject(exists: false);
        $this->bucket->method('object')->with('uploads/readme.txt')->willReturn($stub);

        $this->expectException(UnableToReadFile::class);
        $this->makeAdapter()->read('readme.txt');
    }

    /** read melempar UnableToReadFile ketika GCS melempar exception saat download */
    public function test_read_throws_UnableToReadFile_on_gcs_exception(): void
    {
        $stub = $this->makeObject(exists: true);
        $stub->method('downloadAsString')
            ->willThrowException(new \RuntimeException('network error'));
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToReadFile::class);
        $this->makeAdapter()->read('readme.txt');
    }

    // ---------------------------------------------------------------
    // readStream
    // ---------------------------------------------------------------

    /** readStream mengembalikan PHP resource melalui detach() dari PSR-7 stream */
    public function test_readStream_returns_resource_via_psr7_detach(): void
    {
        $tempFile = tmpfile();
        fwrite($tempFile, 'stream content');
        rewind($tempFile);

        $psr7Stream = $this->createStub(StreamInterface::class);
        $psr7Stream->method('detach')->willReturn($tempFile);

        $stub = $this->makeObject(exists: true);
        $stub->method('downloadAsStream')->willReturn($psr7Stream);
        $this->bucket->method('object')->willReturn($stub);

        $result = $this->makeAdapter()->readStream('file.txt');

        $this->assertIsResource($result);
        $this->assertSame('stream content', stream_get_contents($result));

        fclose($tempFile);
    }

    /** readStream menggunakan memori fallback ketika detach() mengembalikan null */
    public function test_readStream_uses_memory_fallback_when_detach_returns_null(): void
    {
        $psr7Stream = $this->createStub(StreamInterface::class);
        $psr7Stream->method('detach')->willReturn(null);
        $psr7Stream->method('__toString')->willReturn('fallback content');

        $stub = $this->makeObject(exists: true);
        $stub->method('downloadAsStream')->willReturn($psr7Stream);
        $this->bucket->method('object')->willReturn($stub);

        $result = $this->makeAdapter()->readStream('file.txt');

        $this->assertIsResource($result);
        $this->assertSame('fallback content', stream_get_contents($result));
    }

    /** readStream menggunakan memori fallback ketika detach() mengembalikan bukan resource */
    public function test_readStream_uses_memory_fallback_when_detach_returns_non_resource(): void
    {
        $psr7Stream = $this->createStub(StreamInterface::class);
        $psr7Stream->method('detach')->willReturn(false);
        $psr7Stream->method('__toString')->willReturn('content from string');

        $stub = $this->makeObject(exists: true);
        $stub->method('downloadAsStream')->willReturn($psr7Stream);
        $this->bucket->method('object')->willReturn($stub);

        $result = $this->makeAdapter()->readStream('file.txt');

        $this->assertIsResource($result);
    }

    /** readStream melempar UnableToReadFile ketika file tidak ada */
    public function test_readStream_throws_UnableToReadFile_when_file_missing(): void
    {
        $stub = $this->makeObject(exists: false);
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToReadFile::class);
        $this->makeAdapter()->readStream('file.txt');
    }

    /** readStream melempar UnableToReadFile ketika GCS melempar exception */
    public function test_readStream_throws_UnableToReadFile_on_gcs_exception(): void
    {
        $stub = $this->makeObject(exists: true);
        $stub->method('downloadAsStream')
            ->willThrowException(new \RuntimeException('stream error'));
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToReadFile::class);
        $this->makeAdapter()->readStream('file.txt');
    }

    // ---------------------------------------------------------------
    // delete
    // ---------------------------------------------------------------

    /** delete menghapus objek yang ada dengan memanggil delete() satu kali */
    public function test_delete_removes_existing_object(): void
    {
        // createMock diperlukan karena kita set expects()
        $mock = $this->createMock(StorageObject::class);
        $mock->method('exists')->willReturn(true);
        $mock->expects($this->once())->method('delete');
        $this->bucket->method('object')->with('uploads/file.txt')->willReturn($mock);

        $this->makeAdapter()->delete('file.txt');
    }

    /** delete tidak memanggil delete() ketika file tidak ada — tidak melempar exception */
    public function test_delete_skips_when_file_does_not_exist(): void
    {
        // createMock diperlukan karena kita set expects(never())
        $mock = $this->createMock(StorageObject::class);
        $mock->method('exists')->willReturn(false);
        $mock->expects($this->never())->method('delete');
        $this->bucket->method('object')->willReturn($mock);

        $this->makeAdapter()->delete('ghost.txt');
        $this->assertTrue(true);
    }

    /** delete melempar UnableToDeleteFile ketika GCS melempar exception */
    public function test_delete_throws_UnableToDeleteFile_on_exception(): void
    {
        $stub = $this->makeObject(exists: true);
        $stub->method('delete')
            ->willThrowException(new \RuntimeException('delete failed'));
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToDeleteFile::class);
        $this->makeAdapter()->delete('file.txt');
    }

    // ---------------------------------------------------------------
    // deleteDirectory
    // ---------------------------------------------------------------

    /** deleteDirectory menghapus semua objek di direktori */
    public function test_deleteDirectory_deletes_all_objects_in_directory(): void
    {
        // createMock diperlukan karena kita set expects() pada obj1 dan obj2
        $obj1 = $this->createMock(StorageObject::class);
        $obj2 = $this->createMock(StorageObject::class);
        $obj1->expects($this->once())->method('delete');
        $obj2->expects($this->once())->method('delete');

        $this->bucket->method('objects')
            ->with(['prefix' => 'uploads/images/'])
            ->willReturn($this->makeObjectList([$obj1, $obj2]));

        $this->makeAdapter()->deleteDirectory('images');
    }

    /** deleteDirectory tidak melempar exception ketika direktori kosong */
    public function test_deleteDirectory_handles_empty_directory_silently(): void
    {
        $this->bucket->method('objects')
            ->willReturn($this->makeObjectList([]));

        $this->makeAdapter()->deleteDirectory('empty-dir');
        $this->assertTrue(true);
    }

    /** deleteDirectory membangun prefix path dengan benar (diakhiri '/') */
    public function test_deleteDirectory_builds_correct_prefix_path(): void
    {
        $this->bucket->expects($this->once())
            ->method('objects')
            ->with(['prefix' => 'uploads/docs/'])
            ->willReturn($this->makeObjectList([]));

        $this->makeAdapter()->deleteDirectory('docs');
    }

    /** deleteDirectory melempar UnableToDeleteDirectory ketika GCS melempar exception */
    public function test_deleteDirectory_throws_UnableToDeleteDirectory_on_exception(): void
    {
        $this->bucket->method('objects')
            ->willThrowException(new \RuntimeException('GCS error'));

        $this->expectException(UnableToDeleteDirectory::class);
        $this->makeAdapter()->deleteDirectory('images');
    }

    // ---------------------------------------------------------------
    // createDirectory
    // ---------------------------------------------------------------

    /** createDirectory mengunggah objek placeholder kosong diakhiri '/' */
    public function test_createDirectory_uploads_empty_placeholder_object(): void
    {
        $this->bucket->expects($this->once())
            ->method('upload')
            ->with('', ['name' => 'uploads/new-dir/'])
            ->willReturn($this->makeObject());

        $this->makeAdapter()->createDirectory('new-dir', new Config());
    }

    /** createDirectory membangun path placeholder dengan benar untuk path bersarang */
    public function test_createDirectory_builds_correct_nested_placeholder(): void
    {
        $this->bucket->expects($this->once())
            ->method('upload')
            ->with('', ['name' => 'uploads/a/b/c/'])
            ->willReturn($this->makeObject());

        $this->makeAdapter()->createDirectory('a/b/c', new Config());
    }

    /** createDirectory tanpa prefix membuat placeholder langsung di root bucket */
    public function test_createDirectory_without_prefix_creates_root_placeholder(): void
    {
        $this->bucket->expects($this->once())
            ->method('upload')
            ->with('', ['name' => 'my-dir/'])
            ->willReturn($this->makeObject());

        $this->makeAdapter('')->createDirectory('my-dir', new Config());
    }

    /** createDirectory melempar UnableToCreateDirectory ketika GCS melempar exception */
    public function test_createDirectory_throws_UnableToCreateDirectory_on_exception(): void
    {
        $this->bucket->method('upload')
            ->willThrowException(new \RuntimeException('quota exceeded'));

        $this->expectException(UnableToCreateDirectory::class);
        $this->makeAdapter()->createDirectory('new-dir', new Config());
    }

    // ---------------------------------------------------------------
    // setVisibility
    // ---------------------------------------------------------------

    /** setVisibility PUBLIC menggunakan predefinedAcl=publicRead */
    public function test_setVisibility_sets_publicRead_for_public(): void
    {
        // createMock diperlukan karena kita set expects() pada update()
        $mock = $this->createMock(StorageObject::class);
        $mock->expects($this->once())
            ->method('update')
            ->with(['acl' => []], ['predefinedAcl' => 'publicRead']);
        $this->bucket->method('object')->with('uploads/file.txt')->willReturn($mock);

        $this->makeAdapter()->setVisibility('file.txt', Visibility::PUBLIC);
    }

    /** setVisibility PRIVATE menggunakan predefinedAcl=projectPrivate */
    public function test_setVisibility_sets_projectPrivate_for_private(): void
    {
        // createMock diperlukan karena kita set expects() pada update()
        $mock = $this->createMock(StorageObject::class);
        $mock->expects($this->once())
            ->method('update')
            ->with(['acl' => []], ['predefinedAcl' => 'projectPrivate']);
        $this->bucket->method('object')->with('uploads/file.txt')->willReturn($mock);

        $this->makeAdapter()->setVisibility('file.txt', Visibility::PRIVATE);
    }

    /** setVisibility melempar UnableToSetVisibility ketika GCS melempar exception */
    public function test_setVisibility_throws_UnableToSetVisibility_on_exception(): void
    {
        $stub = $this->makeObject();
        $stub->method('update')
            ->willThrowException(new \RuntimeException('ACL error'));
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToSetVisibility::class);
        $this->makeAdapter()->setVisibility('file.txt', Visibility::PUBLIC);
    }

    // ---------------------------------------------------------------
    // visibility
    // ---------------------------------------------------------------

    /** visibility mengembalikan PUBLIC ketika allUsers memiliki role READER */
    public function test_visibility_returns_public_when_allUsers_is_READER(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('get')
            ->willReturn(['role' => 'READER']);

        $stub = $this->makeObject();
        $stub->method('acl')->willReturn($acl);
        $this->bucket->method('object')->willReturn($stub);

        $result = $this->makeAdapter()->visibility('file.txt');

        $this->assertInstanceOf(FileAttributes::class, $result);
        $this->assertSame(Visibility::PUBLIC, $result->visibility());
    }

    /** visibility mengembalikan PRIVATE ketika allUsers tidak memiliki role READER */
    public function test_visibility_returns_private_when_allUsers_not_READER(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('get')->willReturn(['role' => 'OWNER']);

        $stub = $this->makeObject();
        $stub->method('acl')->willReturn($acl);
        $this->bucket->method('object')->willReturn($stub);

        $result = $this->makeAdapter()->visibility('file.txt');

        $this->assertSame(Visibility::PRIVATE, $result->visibility());
    }

    /** visibility mengembalikan PRIVATE ketika ACL tidak memiliki key 'role' */
    public function test_visibility_returns_private_when_role_key_missing(): void
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('get')->willReturn([]);

        $stub = $this->makeObject();
        $stub->method('acl')->willReturn($acl);
        $this->bucket->method('object')->willReturn($stub);

        $result = $this->makeAdapter()->visibility('file.txt');

        $this->assertSame(Visibility::PRIVATE, $result->visibility());
    }

    /** visibility mengembalikan PRIVATE secara diam-diam ketika GCS melempar exception */
    public function test_visibility_returns_private_silently_on_acl_exception(): void
    {
        $stub = $this->makeObject();
        $stub->method('acl')
            ->willThrowException(new \RuntimeException('forbidden'));
        $this->bucket->method('object')->willReturn($stub);

        $result = $this->makeAdapter()->visibility('file.txt');

        $this->assertSame(Visibility::PRIVATE, $result->visibility());
    }

    // ---------------------------------------------------------------
    // mimeType
    // ---------------------------------------------------------------

    /** mimeType mengembalikan FileAttributes dengan contentType yang benar */
    public function test_mimeType_returns_correct_mime_type(): void
    {
        $stub = $this->makeObject(exists: true, info: ['contentType' => 'image/png']);
        $this->bucket->method('object')->willReturn($stub);

        $result = $this->makeAdapter()->mimeType('photo.png');

        $this->assertInstanceOf(FileAttributes::class, $result);
        $this->assertSame('image/png', $result->mimeType());
    }

    /** mimeType melempar UnableToRetrieveMetadata ketika file tidak ada */
    public function test_mimeType_throws_UnableToRetrieveMetadata_when_file_missing(): void
    {
        $stub = $this->makeObject(exists: false);
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToRetrieveMetadata::class);
        $this->makeAdapter()->mimeType('missing.png');
    }

    /** mimeType melempar UnableToRetrieveMetadata ketika 'contentType' tidak ada di info */
    public function test_mimeType_throws_UnableToRetrieveMetadata_when_contentType_missing(): void
    {
        $stub = $this->makeObject(exists: true, info: []);
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToRetrieveMetadata::class);
        $this->makeAdapter()->mimeType('photo.png');
    }

    /** mimeType melempar UnableToRetrieveMetadata ketika contentType adalah string kosong */
    public function test_mimeType_throws_when_contentType_is_empty_string(): void
    {
        $stub = $this->makeObject(exists: true, info: ['contentType' => '']);
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToRetrieveMetadata::class);
        $this->makeAdapter()->mimeType('photo.png');
    }

    /** mimeType melempar UnableToRetrieveMetadata ketika GCS melempar exception */
    public function test_mimeType_throws_UnableToRetrieveMetadata_on_gcs_exception(): void
    {
        $stub = $this->makeObject(exists: true);
        $stub->method('info')
            ->willThrowException(new \RuntimeException('GCS error'));
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToRetrieveMetadata::class);
        $this->makeAdapter()->mimeType('photo.png');
    }

    // ---------------------------------------------------------------
    // lastModified
    // ---------------------------------------------------------------

    /** lastModified mengembalikan FileAttributes dengan Unix timestamp yang benar */
    public function test_lastModified_returns_correct_timestamp(): void
    {
        $updated = '2025-06-15T10:30:00Z';
        $stub    = $this->makeObject(exists: true, info: ['updated' => $updated]);
        $this->bucket->method('object')->willReturn($stub);

        $result = $this->makeAdapter()->lastModified('file.txt');

        $this->assertInstanceOf(FileAttributes::class, $result);
        $this->assertSame(strtotime($updated), $result->lastModified());
    }

    /** lastModified mengembalikan null timestamp ketika 'updated' tidak ada di info */
    public function test_lastModified_returns_null_timestamp_when_updated_missing(): void
    {
        $stub = $this->makeObject(exists: true, info: []);
        $this->bucket->method('object')->willReturn($stub);

        $result = $this->makeAdapter()->lastModified('file.txt');

        $this->assertNull($result->lastModified());
    }

    /** lastModified melempar UnableToRetrieveMetadata ketika file tidak ada */
    public function test_lastModified_throws_UnableToRetrieveMetadata_when_file_missing(): void
    {
        $stub = $this->makeObject(exists: false);
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToRetrieveMetadata::class);
        $this->makeAdapter()->lastModified('file.txt');
    }

    /** lastModified melempar UnableToRetrieveMetadata ketika GCS melempar exception */
    public function test_lastModified_throws_UnableToRetrieveMetadata_on_gcs_exception(): void
    {
        $stub = $this->makeObject(exists: true);
        $stub->method('info')
            ->willThrowException(new \RuntimeException('GCS error'));
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToRetrieveMetadata::class);
        $this->makeAdapter()->lastModified('file.txt');
    }

    // ---------------------------------------------------------------
    // fileSize
    // ---------------------------------------------------------------

    /** fileSize mengembalikan ukuran file dalam bytes yang benar */
    public function test_fileSize_returns_correct_size_in_bytes(): void
    {
        $stub = $this->makeObject(exists: true, info: ['size' => '2048']);
        $this->bucket->method('object')->willReturn($stub);

        $result = $this->makeAdapter()->fileSize('file.txt');

        $this->assertInstanceOf(FileAttributes::class, $result);
        $this->assertSame(2048, $result->fileSize());
    }

    /** fileSize mengembalikan 0 ketika 'size' tidak ada di info */
    public function test_fileSize_returns_zero_when_size_missing_from_info(): void
    {
        $stub = $this->makeObject(exists: true, info: []);
        $this->bucket->method('object')->willReturn($stub);

        $result = $this->makeAdapter()->fileSize('file.txt');

        $this->assertSame(0, $result->fileSize());
    }

    /** fileSize melempar UnableToRetrieveMetadata ketika file tidak ada */
    public function test_fileSize_throws_UnableToRetrieveMetadata_when_file_missing(): void
    {
        $stub = $this->makeObject(exists: false);
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToRetrieveMetadata::class);
        $this->makeAdapter()->fileSize('file.txt');
    }

    /** fileSize melempar UnableToRetrieveMetadata ketika GCS melempar exception */
    public function test_fileSize_throws_UnableToRetrieveMetadata_on_gcs_exception(): void
    {
        $stub = $this->makeObject(exists: true);
        $stub->method('info')
            ->willThrowException(new \RuntimeException('GCS error'));
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToRetrieveMetadata::class);
        $this->makeAdapter()->fileSize('file.txt');
    }

    // ---------------------------------------------------------------
    // listContents
    // ---------------------------------------------------------------

    /** listContents non-deep mengirim options dengan delimiter='/' */
    public function test_listContents_non_deep_sends_delimiter_option(): void
    {
        $this->bucket->expects($this->once())
            ->method('objects')
            ->with(['prefix' => 'uploads/', 'delimiter' => '/'])
            ->willReturn($this->makeObjectList([]));

        iterator_to_array($this->makeAdapter()->listContents('', false));
    }

    /** listContents deep tidak mengirim delimiter dalam options */
    public function test_listContents_deep_sends_no_delimiter(): void
    {
        $this->bucket->expects($this->once())
            ->method('objects')
            ->with(['prefix' => 'uploads/'])
            ->willReturn($this->makeObjectList([]));

        iterator_to_array($this->makeAdapter()->listContents('', true));
    }

    /** listContents non-deep menghasilkan FileAttributes dan DirectoryAttributes yang benar */
    public function test_listContents_non_deep_yields_files_and_directories(): void
    {
        // Hanya stub — tidak ada expects()
        $fileStub = $this->createStub(StorageObject::class);
        $fileStub->method('name')->willReturn('uploads/images/photo.jpg');
        $fileStub->method('info')->willReturn([
            'size'        => '4096',
            'updated'     => '2025-03-01T08:00:00Z',
            'contentType' => 'image/jpeg',
        ]);

        $list = $this->makeObjectList([$fileStub], ['uploads/images/docs/']);

        $this->bucket->method('objects')
            ->with(['prefix' => 'uploads/images/', 'delimiter' => '/'])
            ->willReturn($list);

        $results = iterator_to_array($this->makeAdapter()->listContents('images', false), false);

        $this->assertCount(2, $results);
        $this->assertInstanceOf(FileAttributes::class, $results[0]);
        $this->assertInstanceOf(DirectoryAttributes::class, $results[1]);
        $this->assertSame('images/photo.jpg', $results[0]->path());
        $this->assertSame(4096, $results[0]->fileSize());
        $this->assertSame('image/jpeg', $results[0]->mimeType());
        $this->assertSame('images/docs', $results[1]->path());
    }

    /** listContents deep menghasilkan semua file secara rekursif tanpa batas delimiter */
    public function test_listContents_deep_yields_all_nested_files(): void
    {
        $f1 = $this->createStub(StorageObject::class);
        $f1->method('name')->willReturn('uploads/a/b/deep.txt');
        $f1->method('info')->willReturn(['size' => '512', 'updated' => '2025-01-01T00:00:00Z', 'contentType' => 'text/plain']);

        $f2 = $this->createStub(StorageObject::class);
        $f2->method('name')->willReturn('uploads/a/c/other.txt');
        $f2->method('info')->willReturn(['size' => '256', 'updated' => '2025-02-01T00:00:00Z', 'contentType' => 'text/plain']);

        $this->bucket->method('objects')
            ->with(['prefix' => 'uploads/a/'])
            ->willReturn($this->makeObjectList([$f1, $f2]));

        $results = iterator_to_array($this->makeAdapter()->listContents('a', true), false);

        $this->assertCount(2, $results);
        $this->assertSame('a/b/deep.txt', $results[0]->path());
        $this->assertSame('a/c/other.txt', $results[1]->path());
    }

    /** listContents menghasilkan DirectoryAttributes untuk objek yang namanya diakhiri '/' */
    public function test_listContents_yields_DirectoryAttributes_for_trailing_slash_objects(): void
    {
        $dirStub = $this->createStub(StorageObject::class);
        $dirStub->method('name')->willReturn('uploads/my-dir/');
        $dirStub->method('info')->willReturn([]);

        $this->bucket->method('objects')
            ->willReturn($this->makeObjectList([$dirStub]));

        $results = iterator_to_array($this->makeAdapter()->listContents('', true), false);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(DirectoryAttributes::class, $results[0]);
        $this->assertSame('my-dir', $results[0]->path());
    }

    /** listContents menghapus prefix dari semua path hasil */
    public function test_listContents_strips_prefix_from_result_paths(): void
    {
        $fileStub = $this->createStub(StorageObject::class);
        $fileStub->method('name')->willReturn('uploads/docs/report.pdf');
        $fileStub->method('info')->willReturn(['size' => '1024', 'updated' => '2025-01-01T00:00:00Z', 'contentType' => 'application/pdf']);

        $this->bucket->method('objects')->willReturn($this->makeObjectList([$fileStub]));

        $results = iterator_to_array($this->makeAdapter()->listContents('docs', true), false);

        $this->assertSame('docs/report.pdf', $results[0]->path());
    }

    /** listContents dengan adapter tanpa prefix tidak menambahkan prefix pada path */
    public function test_listContents_without_prefix_uses_raw_path(): void
    {
        $fileStub = $this->createStub(StorageObject::class);
        $fileStub->method('name')->willReturn('images/photo.jpg');
        $fileStub->method('info')->willReturn(['size' => '100', 'updated' => '2025-01-01T00:00:00Z', 'contentType' => 'image/jpeg']);

        $this->bucket->method('objects')
            ->with(['prefix' => ''])
            ->willReturn($this->makeObjectList([$fileStub]));

        $results = iterator_to_array($this->makeAdapter('')->listContents('', true), false);

        $this->assertSame('images/photo.jpg', $results[0]->path());
    }

    /** listContents tidak memanggil prefixes() ketika deep=true */
    public function test_listContents_deep_does_not_call_prefixes(): void
    {
        $innerList = $this->makeObjectList([]);

        $strictList = new class($innerList) implements \Iterator {
            public function __construct(private object $inner) {}
            public function prefixes(): array
            {
                throw new \BadMethodCallException('prefixes() tidak boleh dipanggil saat deep=true');
            }
            public function current(): mixed { return $this->inner->current(); }
            public function key(): mixed     { return $this->inner->key(); }
            public function next(): void     { $this->inner->next(); }
            public function rewind(): void   { $this->inner->rewind(); }
            public function valid(): bool    { return $this->inner->valid(); }
        };

        $this->bucket->method('objects')->willReturn($strictList);

        iterator_to_array($this->makeAdapter()->listContents('', true), false);
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // move
    // ---------------------------------------------------------------

    /** move menyalin ke tujuan lalu menghapus sumber */
    public function test_move_copies_to_destination_then_deletes_source(): void
    {
        // createMock diperlukan karena kita set expects() pada copy() dan delete()
        $mock = $this->createMock(StorageObject::class);
        $mock->method('exists')->willReturn(true);
        $mock->expects($this->once())
            ->method('copy')
            ->with($this->bucket, ['name' => 'uploads/dest.txt']);
        $mock->expects($this->once())->method('delete');
        $this->bucket->method('object')->willReturn($mock);

        $this->makeAdapter()->move('source.txt', 'dest.txt', new Config());
    }

    /** move membangun path sumber dan tujuan dengan prefix yang benar */
    public function test_move_builds_correct_prefixed_paths(): void
    {
        // createMock diperlukan karena kita set expects() pada copy()
        $mock = $this->createMock(StorageObject::class);
        $mock->method('exists')->willReturn(true);
        $mock->expects($this->once())
            ->method('copy')
            ->with($this->bucket, ['name' => 'uploads/new/dest.txt']);
        $this->bucket->method('object')
            ->with('uploads/old/source.txt')
            ->willReturn($mock);

        $this->makeAdapter()->move('old/source.txt', 'new/dest.txt', new Config());
    }

    /** move melempar UnableToMoveFile ketika file sumber tidak ada */
    public function test_move_throws_UnableToMoveFile_when_source_missing(): void
    {
        $stub = $this->makeObject(exists: false);
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToMoveFile::class);
        $this->makeAdapter()->move('ghost.txt', 'dest.txt', new Config());
    }

    /** move melempar UnableToMoveFile ketika GCS melempar exception saat copy */
    public function test_move_throws_UnableToMoveFile_on_copy_exception(): void
    {
        $stub = $this->makeObject(exists: true);
        $stub->method('copy')
            ->willThrowException(new \RuntimeException('copy failed'));
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToMoveFile::class);
        $this->makeAdapter()->move('source.txt', 'dest.txt', new Config());
    }

    /** move melempar UnableToMoveFile ketika GCS melempar exception saat delete sumber */
    public function test_move_throws_UnableToMoveFile_on_delete_exception(): void
    {
        $stub = $this->makeObject(exists: true);
        $stub->method('delete')
            ->willThrowException(new \RuntimeException('delete failed'));
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToMoveFile::class);
        $this->makeAdapter()->move('source.txt', 'dest.txt', new Config());
    }

    // ---------------------------------------------------------------
    // copy
    // ---------------------------------------------------------------

    /** copy menyalin objek ke lokasi tujuan */
    public function test_copy_copies_object_to_destination(): void
    {
        // createMock diperlukan karena kita set expects() pada copy()
        $mock = $this->createMock(StorageObject::class);
        $mock->method('exists')->willReturn(true);
        $mock->expects($this->once())
            ->method('copy')
            ->with($this->bucket, ['name' => 'uploads/dest.txt']);
        $this->bucket->method('object')->willReturn($mock);

        $this->makeAdapter()->copy('source.txt', 'dest.txt', new Config());
    }

    /** copy membangun path sumber dan tujuan dengan prefix yang benar */
    public function test_copy_builds_correct_prefixed_paths(): void
    {
        // createMock diperlukan karena kita set expects() pada copy()
        $mock = $this->createMock(StorageObject::class);
        $mock->method('exists')->willReturn(true);
        $mock->expects($this->once())
            ->method('copy')
            ->with($this->bucket, ['name' => 'uploads/b/copy.txt']);
        $this->bucket->method('object')
            ->with('uploads/a/original.txt')
            ->willReturn($mock);

        $this->makeAdapter()->copy('a/original.txt', 'b/copy.txt', new Config());
    }

    /** copy melempar UnableToCopyFile ketika file sumber tidak ada */
    public function test_copy_throws_UnableToCopyFile_when_source_missing(): void
    {
        $stub = $this->makeObject(exists: false);
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToCopyFile::class);
        $this->makeAdapter()->copy('ghost.txt', 'dest.txt', new Config());
    }

    /** copy melempar UnableToCopyFile ketika GCS melempar exception */
    public function test_copy_throws_UnableToCopyFile_on_gcs_exception(): void
    {
        $stub = $this->makeObject(exists: true);
        $stub->method('copy')
            ->willThrowException(new \RuntimeException('copy error'));
        $this->bucket->method('object')->willReturn($stub);

        $this->expectException(UnableToCopyFile::class);
        $this->makeAdapter()->copy('source.txt', 'dest.txt', new Config());
    }

    // ---------------------------------------------------------------
    // getUrl
    // ---------------------------------------------------------------

    /** getUrl menghasilkan URL publik yang benar dengan prefix */
    public function test_getUrl_returns_correct_public_url_with_prefix(): void
    {
        $this->assertSame(
            'https://storage.googleapis.com/test-bucket/uploads/images/photo.jpg',
            $this->makeAdapter()->getUrl('images/photo.jpg'),
        );
    }

    /** getUrl menghasilkan URL publik yang benar tanpa prefix */
    public function test_getUrl_returns_correct_public_url_without_prefix(): void
    {
        $this->assertSame(
            'https://storage.googleapis.com/test-bucket/images/photo.jpg',
            $this->makeAdapter('')->getUrl('images/photo.jpg'),
        );
    }

    /** getUrl menggunakan storage API URI kustom yang dikonfigurasi */
    public function test_getUrl_uses_custom_storage_api_uri(): void
    {
        $adapter = new GoogleCloudStorageAdapter(
            client:            $this->client,
            bucketName:        self::BUCKET,
            pathPrefix:        '',
            storageApiUri:     'https://cdn.example.com',
            defaultVisibility: Visibility::PUBLIC,
        );

        $this->assertSame(
            'https://cdn.example.com/test-bucket/photo.jpg',
            $adapter->getUrl('photo.jpg'),
        );
    }

    /** getUrl memotong trailing slash pada storage API URI */
    public function test_getUrl_trims_trailing_slash_on_storage_api_uri(): void
    {
        $adapter = new GoogleCloudStorageAdapter(
            client:            $this->client,
            bucketName:        self::BUCKET,
            pathPrefix:        '',
            storageApiUri:     'https://storage.googleapis.com/',
            defaultVisibility: Visibility::PUBLIC,
        );

        $url = $adapter->getUrl('file.txt');

        $this->assertSame('https://storage.googleapis.com/test-bucket/file.txt', $url);
    }

    /** getUrl menghapus slash di awal path sehingga tidak ada double slash */
    public function test_getUrl_strips_leading_slash_from_path(): void
    {
        $this->assertSame(
            'https://storage.googleapis.com/test-bucket/photo.jpg',
            $this->makeAdapter('')->getUrl('/photo.jpg'),
        );
    }

    // ---------------------------------------------------------------
    // signedUrl
    // ---------------------------------------------------------------

    /** signedUrl dengan expiration integer meneruskan DateTimeInterface ke GCS */
    public function test_signedUrl_with_integer_expiration_passes_datetime_to_gcs(): void
    {
        // createMock diperlukan karena kita set expects() pada signedUrl()
        $mock = $this->createMock(StorageObject::class);
        $mock->expects($this->once())
            ->method('signedUrl')
            ->with($this->isInstanceOf(\DateTimeInterface::class))
            ->willReturn('https://storage.googleapis.com/signed?token=abc123');
        $this->bucket->method('object')->with('uploads/private.pdf')->willReturn($mock);

        $result = $this->makeAdapter()->signedUrl('private.pdf', 3600);

        $this->assertSame('https://storage.googleapis.com/signed?token=abc123', $result);
    }

    /** signedUrl dengan DateTimeInterface meneruskan instance yang sama ke GCS */
    public function test_signedUrl_with_DateTimeInterface_passes_it_directly(): void
    {
        $expires = new DateTimeImmutable('+2 hours');

        // createMock diperlukan karena kita set expects() pada signedUrl()
        $mock = $this->createMock(StorageObject::class);
        $mock->expects($this->once())
            ->method('signedUrl')
            ->with($expires)
            ->willReturn('https://storage.googleapis.com/signed?token=xyz789');
        $this->bucket->method('object')->willReturn($mock);

        $result = $this->makeAdapter()->signedUrl('private.pdf', $expires);

        $this->assertSame('https://storage.googleapis.com/signed?token=xyz789', $result);
    }

    /** signedUrl dengan expiration default (3600 detik) menggunakan ~1 jam dari sekarang */
    public function test_signedUrl_with_default_expiration_uses_one_hour(): void
    {
        $before = new \DateTime('+3599 seconds');
        $after  = new \DateTime('+3601 seconds');

        // createMock diperlukan karena kita set expects() pada signedUrl()
        $mock = $this->createMock(StorageObject::class);
        $mock->expects($this->once())
            ->method('signedUrl')
            ->with($this->callback(function (\DateTimeInterface $dt) use ($before, $after): bool {
                return $dt >= $before && $dt <= $after;
            }))
            ->willReturn('https://signed.url');
        $this->bucket->method('object')->willReturn($mock);

        $this->makeAdapter()->signedUrl('file.txt');
    }

    // ---------------------------------------------------------------
    // getBucket / getClient
    // ---------------------------------------------------------------

    /** getBucket mengembalikan instance Bucket yang dikonfigurasi */
    public function test_getBucket_returns_configured_bucket_instance(): void
    {
        $this->assertSame($this->bucket, $this->makeAdapter()->getBucket());
    }

    /** getClient mengembalikan instance StorageClient yang dikonfigurasi */
    public function test_getClient_returns_configured_client_instance(): void
    {
        $this->assertSame($this->client, $this->makeAdapter()->getClient());
    }

    // ---------------------------------------------------------------
    // Path prefix behavior
    // ---------------------------------------------------------------

    /** Prefix dengan trailing slash tidak menghasilkan double slash pada path */
    public function test_prefix_with_trailing_slash_does_not_produce_double_slash(): void
    {
        $adapter = new GoogleCloudStorageAdapter(
            client:            $this->client,
            bucketName:        self::BUCKET,
            pathPrefix:        'uploads/',
            storageApiUri:     self::STORAGE_URI,
            defaultVisibility: Visibility::PUBLIC,
        );

        $stub = $this->makeObject(exists: true);
        $this->bucket->method('object')->with('uploads/file.txt')->willReturn($stub);

        $this->assertTrue($adapter->fileExists('file.txt'));
    }

    /** Path dengan leading slash di-strip dengan benar sebelum ditambah prefix */
    public function test_path_with_leading_slash_is_stripped_before_prefixing(): void
    {
        $stub = $this->makeObject(exists: true);
        $this->bucket->method('object')->with('uploads/file.txt')->willReturn($stub);

        $this->assertTrue($this->makeAdapter()->fileExists('/file.txt'));
    }

    /** Adapter tanpa prefix menggunakan path mentah tanpa modifikasi */
    public function test_adapter_without_prefix_uses_raw_path(): void
    {
        $stub = $this->makeObject(exists: true);
        $this->bucket->method('object')->with('images/photo.jpg')->willReturn($stub);

        $this->assertTrue($this->makeAdapter('')->fileExists('images/photo.jpg'));
    }

    /** getUrl dengan prefix yang dalam menghasilkan URL dengan full nested path */
    public function test_getUrl_with_deep_nested_prefix_builds_correct_url(): void
    {
        $adapter = new GoogleCloudStorageAdapter(
            client:            $this->client,
            bucketName:        self::BUCKET,
            pathPrefix:        'tenant/prod/assets',
            storageApiUri:     self::STORAGE_URI,
            defaultVisibility: Visibility::PUBLIC,
        );

        $this->assertSame(
            'https://storage.googleapis.com/test-bucket/tenant/prod/assets/profile/avatar.png',
            $adapter->getUrl('profile/avatar.png'),
        );
    }
}
