<?php

namespace Arshad1114\DmsDisk\Tests\Unit;

use Arshad1114\DmsDisk\DmsClient;
use Arshad1114\DmsDisk\DmsDriver;
use Arshad1114\DmsDisk\Exceptions\DmsException;
use Arshad1114\DmsDisk\Exceptions\DmsFileNotFoundException;
use League\Flysystem\Config;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DmsDriverTest extends TestCase
{
    protected DmsDriver $driver;

    /** @var DmsClient&MockObject */
    protected DmsClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(DmsClient::class);
        $this->driver = new DmsDriver($this->client);
    }

    // -------------------------------------------------------------------------
    // fileExists
    // -------------------------------------------------------------------------

    public function test_file_exists_returns_true(): void
    {
        $this->client->expects($this->once())
            ->method('exists')
            ->with('docs/file.txt')
            ->willReturn(true);

        $this->assertTrue($this->driver->fileExists('docs/file.txt'));
    }

    public function test_file_exists_returns_false(): void
    {
        $this->client->expects($this->once())
            ->method('exists')
            ->willReturn(false);

        $this->assertFalse($this->driver->fileExists('docs/missing.txt'));
    }

    // -------------------------------------------------------------------------
    // read / readStream
    // -------------------------------------------------------------------------

    public function test_read_returns_contents(): void
    {
        $this->client->expects($this->once())
            ->method('download')
            ->with('docs/file.txt')
            ->willReturn('file contents');

        $this->assertSame('file contents', $this->driver->read('docs/file.txt'));
    }

    public function test_read_throws_unable_to_read_on_not_found(): void
    {
        $this->client->method('download')
            ->willThrowException(DmsFileNotFoundException::atPath('docs/missing.txt'));

        $this->expectException(UnableToReadFile::class);

        $this->driver->read('docs/missing.txt');
    }

    public function test_read_stream_returns_resource(): void
    {
        $this->client->expects($this->once())
            ->method('downloadStream')
            ->with('docs/file.txt')
            ->willReturnCallback(function () {
                $stream = fopen('php://temp', 'r+');
                fwrite($stream, 'stream content');
                rewind($stream);
                return $stream;
            });

        $stream = $this->driver->readStream('docs/file.txt');

        $this->assertIsResource($stream);
        $this->assertSame('stream content', stream_get_contents($stream));

        fclose($stream);
    }

    // -------------------------------------------------------------------------
    // write
    // -------------------------------------------------------------------------

    public function test_write_delegates_to_client(): void
    {
        $this->client->expects($this->once())
            ->method('upload')
            ->with('docs/file.txt', 'content', 'private')
            ->willReturn(['status' => 'ok']);

        $this->driver->write('docs/file.txt', 'content', new Config());
    }

    public function test_write_throws_unable_to_write_on_failure(): void
    {
        $this->client->method('upload')
            ->willThrowException(new DmsException('write error'));

        $this->expectException(UnableToWriteFile::class);

        $this->driver->write('docs/file.txt', 'content', new Config());
    }

    // -------------------------------------------------------------------------
    // delete
    // -------------------------------------------------------------------------

    public function test_delete_delegates_to_client(): void
    {
        $this->client->expects($this->once())
            ->method('delete')
            ->with('docs/file.txt');

        $this->driver->delete('docs/file.txt');
    }

    public function test_delete_is_noop_when_file_not_found(): void
    {
        $this->client->method('delete')
            ->willThrowException(DmsFileNotFoundException::atPath('docs/file.txt'));

        // Should NOT throw — Flysystem contract treats missing file as success
        $this->driver->delete('docs/file.txt');
        $this->addToAssertionCount(1);
    }

    public function test_delete_throws_unable_to_delete_on_other_failure(): void
    {
        $this->client->method('delete')
            ->willThrowException(new DmsException('server error'));

        $this->expectException(UnableToDeleteFile::class);

        $this->driver->delete('docs/file.txt');
    }

    // -------------------------------------------------------------------------
    // copy (driver implements as download + upload)
    // -------------------------------------------------------------------------

    public function test_copy_downloads_source_and_uploads_to_destination(): void
    {
        $this->client->expects($this->once())
            ->method('download')
            ->with('docs/a.txt')
            ->willReturn('content');

        $this->client->expects($this->once())
            ->method('upload')
            ->with('docs/b.txt', 'content', 'private');

        $this->driver->copy('docs/a.txt', 'docs/b.txt', new Config());
    }

    public function test_copy_throws_unable_to_copy_on_failure(): void
    {
        $this->client->method('download')
            ->willThrowException(new DmsException('copy error'));

        $this->expectException(UnableToCopyFile::class);

        $this->driver->copy('docs/a.txt', 'docs/b.txt', new Config());
    }

    // -------------------------------------------------------------------------
    // move
    // -------------------------------------------------------------------------

    public function test_move_delegates_to_client(): void
    {
        $this->client->expects($this->once())
            ->method('move')
            ->with('docs/a.txt', 'archive/a.txt');

        $this->driver->move('docs/a.txt', 'archive/a.txt', new Config());
    }

    public function test_move_throws_unable_to_move_on_failure(): void
    {
        $this->client->method('move')
            ->willThrowException(new DmsException('move error'));

        $this->expectException(UnableToMoveFile::class);

        $this->driver->move('docs/a.txt', 'archive/a.txt', new Config());
    }

    // -------------------------------------------------------------------------
    // listContents
    // -------------------------------------------------------------------------

    public function test_list_contents_yields_file_attributes(): void
    {
        $this->client->expects($this->once())
            ->method('listFiles')
            ->with('docs', false)
            ->willReturn(['docs/a.txt', 'docs/b.txt']);

        $results = iterator_to_array($this->driver->listContents('docs', false));

        $this->assertCount(2, $results);
        $this->assertSame('docs/a.txt', $results[0]->path());
        $this->assertSame('docs/b.txt', $results[1]->path());
    }

    // -------------------------------------------------------------------------
    // metadata
    // -------------------------------------------------------------------------

    public function test_file_size_returns_file_attributes(): void
    {
        $this->client->expects($this->once())
            ->method('metadata')
            ->with('docs/file.txt')
            ->willReturn(['size' => 1024]);

        $attrs = $this->driver->fileSize('docs/file.txt');

        $this->assertSame(1024, $attrs->fileSize());
    }

    public function test_mime_type_returns_file_attributes(): void
    {
        $this->client->expects($this->once())
            ->method('metadata')
            ->with('docs/file.pdf')
            ->willReturn(['mime_type' => 'application/pdf']);

        $attrs = $this->driver->mimeType('docs/file.pdf');

        $this->assertSame('application/pdf', $attrs->mimeType());
    }

    public function test_last_modified_returns_file_attributes(): void
    {
        $this->client->expects($this->once())
            ->method('metadata')
            ->willReturn(['last_modified' => 1700000000]);

        $attrs = $this->driver->lastModified('docs/file.txt');

        $this->assertSame(1700000000, $attrs->lastModified());
    }

    // -------------------------------------------------------------------------
    // visibility
    // -------------------------------------------------------------------------

    public function test_visibility_delegates_to_client(): void
    {
        $this->client->expects($this->once())
            ->method('visibility')
            ->with('docs/file.txt')
            ->willReturn('private');

        $attrs = $this->driver->visibility('docs/file.txt');

        $this->assertSame('private', $attrs->visibility());
    }

    // -------------------------------------------------------------------------
    // createDirectory (no-op)
    // -------------------------------------------------------------------------

    public function test_create_directory_is_noop(): void
    {
        $this->client->expects($this->never())->method($this->anything());

        $this->driver->createDirectory('docs/new-folder', new Config());
    }
}
