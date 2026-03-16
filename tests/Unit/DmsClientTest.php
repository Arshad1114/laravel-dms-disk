<?php

namespace Arshad1114\DmsDisk\Tests\Unit;

use Arshad1114\DmsDisk\DmsClient;
use Arshad1114\DmsDisk\Exceptions\DmsAuthException;
use Arshad1114\DmsDisk\Exceptions\DmsFileNotFoundException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class DmsClientTest extends TestCase
{
    private DmsClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new DmsClient([
            'url'         => 'https://dms.test',
            'token'       => 'test-token',
            'disk'        => null,
            'timeout'     => 5,
            'retry'       => 1,
            'retry_delay' => 0,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [\Arshad1114\DmsDisk\DmsServiceProvider::class];
    }

    // ── Core CRUD ─────────────────────────────────────────────────────────────

    public function test_upload_sends_correct_request(): void
    {
        Http::fake([
            'dms.test/dms-disk/upload' => Http::response(['status' => 'ok', 'path' => 'test.txt'], 200),
        ]);

        $result = $this->client->upload('test.txt', 'hello world');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://dms.test/dms-disk/upload'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request->isMultipart();
        });

        $this->assertEquals('ok', $result['status']);
    }

    public function test_upload_throws_auth_exception_on_401(): void
    {
        Http::fake([
            'dms.test/dms-disk/upload' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->expectException(DmsAuthException::class);
        $this->client->upload('test.txt', 'hello');
    }

    public function test_download_returns_file_contents(): void
    {
        Http::fake([
            'dms.test/dms-disk/file*' => Http::response('file contents here', 200),
        ]);

        $result = $this->client->download('invoices/001.pdf');
        $this->assertEquals('file contents here', $result);
    }

    public function test_download_throws_not_found_on_404(): void
    {
        Http::fake([
            'dms.test/dms-disk/file*' => Http::response(['message' => 'Not found'], 404),
        ]);

        $this->expectException(DmsFileNotFoundException::class);
        $this->client->download('missing.pdf');
    }

    public function test_exists_returns_true_when_file_exists(): void
    {
        Http::fake([
            'dms.test/dms-disk/exists*' => Http::response(['exists' => true], 200),
        ]);

        $this->assertTrue($this->client->exists('test.txt'));
    }

    public function test_exists_returns_false(): void
    {
        Http::fake([
            'dms.test/dms-disk/exists*' => Http::response(['exists' => false], 200),
        ]);

        $this->assertFalse($this->client->exists('test.txt'));
    }

    public function test_delete_sends_delete_request(): void
    {
        Http::fake([
            'dms.test/dms-disk/file*' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->client->delete('test.txt');

        Http::assertSent(fn(Request $r) => $r->method() === 'DELETE');
    }

    public function test_list_files_returns_array(): void
    {
        Http::fake([
            'dms.test/dms-disk/list*' => Http::response(['files' => ['a.pdf', 'b.pdf']], 200),
        ]);

        $files = $this->client->listFiles('invoices/');
        $this->assertCount(2, $files);
        $this->assertEquals('a.pdf', $files[0]);
    }

    public function test_metadata_returns_array(): void
    {
        Http::fake([
            'dms.test/dms-disk/metadata*' => Http::response([
                'path'          => 'test.txt',
                'size'          => 1024,
                'mime_type'     => 'text/plain',
                'visibility'    => 'private',
                'last_modified' => 1700000000,
            ], 200),
        ]);

        $meta = $this->client->metadata('test.txt');
        $this->assertEquals(1024, $meta['size']);
    }

    public function test_url_returns_string(): void
    {
        Http::fake([
            'dms.test/dms-disk/url*' => Http::response(['url' => 'https://cdn.test/test.txt'], 200),
        ]);

        $this->assertEquals('https://cdn.test/test.txt', $this->client->url('test.txt'));
    }

    public function test_temporary_url_returns_url_and_expiry(): void
    {
        Http::fake([
            'dms.test/dms-disk/temp-url*' => Http::response([
                'url'        => 'https://cdn.test/test.txt?sig=abc',
                'expires_at' => '2025-01-01T00:00:00Z',
            ], 200),
        ]);

        $result = $this->client->temporaryUrl('test.txt', 3600);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('expires_at', $result);
    }

    public function test_move_sends_post_request(): void
    {
        Http::fake([
            'dms.test/dms-disk/move' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->client->move('old.txt', 'new.txt');

        Http::assertSent(fn(Request $r) =>
            $r->method() === 'POST' && str_contains($r->url(), 'move')
        );
    }

    // ── Disk optional tests ───────────────────────────────────────────────────

    public function test_upload_does_not_send_disk_when_not_configured(): void
    {
        Http::fake([
            'dms.test/dms-disk/upload' => Http::response(['status' => 'ok', 'path' => 'test.txt'], 200),
        ]);

        $client = new DmsClient([
            'url'         => 'https://dms.test',
            'token'       => 'test-token',
            'disk'        => null,
            'timeout'     => 5,
            'retry'       => 1,
            'retry_delay' => 0,
        ]);

        $client->upload('test.txt', 'hello');

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'upload')
                && !str_contains($request->body(), 'name="disk"');
        });
    }

    public function test_upload_sends_disk_when_configured(): void
    {
        Http::fake([
            'dms.test/dms-disk/upload' => Http::response(['status' => 'ok', 'path' => 'test.txt'], 200),
        ]);

        $client = new DmsClient([
            'url'         => 'https://dms.test',
            'token'       => 'test-token',
            'disk'        => 'client',
            'timeout'     => 5,
            'retry'       => 1,
            'retry_delay' => 0,
        ]);

        $client->upload('test.txt', 'hello');

        Http::assertSent(function (Request $request) {
            return str_contains($request->body(), 'client');
        });
    }

    public function test_delete_does_not_send_disk_when_not_configured(): void
    {
        Http::fake([
            'dms.test/dms-disk/file*' => Http::response(['status' => 'ok'], 200),
        ]);

        $client = new DmsClient([
            'url'         => 'https://dms.test',
            'token'       => 'test-token',
            'disk'        => null,
            'timeout'     => 5,
            'retry'       => 1,
            'retry_delay' => 0,
        ]);

        $client->delete('test.txt');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'DELETE'
                && !str_contains($request->url(), 'disk=');
        });
    }

    public function test_delete_sends_disk_when_configured(): void
    {
        Http::fake([
            'dms.test/dms-disk/file*' => Http::response(['status' => 'ok'], 200),
        ]);

        $client = new DmsClient([
            'url'         => 'https://dms.test',
            'token'       => 'test-token',
            'disk'        => 'client',
            'timeout'     => 5,
            'retry'       => 1,
            'retry_delay' => 0,
        ]);

        $client->delete('test.txt');

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'disk=client');
        });
    }

    public function test_exists_does_not_send_disk_when_not_configured(): void
    {
        Http::fake([
            'dms.test/dms-disk/exists*' => Http::response(['exists' => true], 200),
        ]);

        $client = new DmsClient([
            'url'         => 'https://dms.test',
            'token'       => 'test-token',
            'disk'        => null,
            'timeout'     => 5,
            'retry'       => 1,
            'retry_delay' => 0,
        ]);

        $client->exists('test.txt');

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'exists')
                && !str_contains($request->url(), 'disk=');
        });
    }

    public function test_exists_sends_disk_when_configured(): void
    {
        Http::fake([
            'dms.test/dms-disk/exists*' => Http::response(['exists' => true], 200),
        ]);

        $client = new DmsClient([
            'url'         => 'https://dms.test',
            'token'       => 'test-token',
            'disk'        => 'client',
            'timeout'     => 5,
            'retry'       => 1,
            'retry_delay' => 0,
        ]);

        $client->exists('test.txt');

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'disk=client');
        });
    }
}