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
            'disk'        => 'local',
            'timeout'     => 5,
            'retry'       => 1,
            'retry_delay' => 0,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [\Arshad1114\DmsDisk\DmsServiceProvider::class];
    }

    public function test_upload_sends_correct_request(): void
    {
        Http::fake([
            'dms.test/dms-disk/upload' => Http::response(['status' => 'ok', 'path' => 'test.txt'], 200),
        ]);

        $result = $this->client->upload('test.txt', 'hello world');

        Http::assertSent(function (Request $request) {
            return $request->url()    === 'https://dms.test/dms-disk/upload'
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
}