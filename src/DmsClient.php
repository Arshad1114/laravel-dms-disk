<?php

namespace Arshad1114\DmsDisk;

use Arshad1114\DmsDisk\Exceptions\DmsAuthException;
use Arshad1114\DmsDisk\Exceptions\DmsConnectionException;
use Arshad1114\DmsDisk\Exceptions\DmsException;
use Arshad1114\DmsDisk\Exceptions\DmsFileNotFoundException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class DmsClient
{
    public function __construct(private readonly array $config) {}

    // -------------------------------------------------------------------------
    // Internal HTTP builder
    // -------------------------------------------------------------------------

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->config['url'], '/'))
            ->withToken($this->config['token'])
            ->timeout((int) $this->config['timeout'])
            ->retry(
                (int) $this->config['retry'],
                (int) $this->config['retry_delay'],
                function (\Exception $e) {
                    // Only retry on connection errors, not on 4xx
                    return $e instanceof \Illuminate\Http\Client\ConnectionException;
                }
            )
            ->acceptJson();
    }

    private function disk(): string
    {
        return $this->config['disk'] ?? 'local';
    }

    // -------------------------------------------------------------------------
    // Exception mapper — converts HTTP status codes to typed exceptions
    // -------------------------------------------------------------------------

    private function handleRequestException(RequestException $e, string $path = ''): never
    {
        $status = $e->response->status();

        throw match (true) {
            $status === 401             => DmsAuthException::invalidToken(),
            $status === 404             => DmsFileNotFoundException::atPath($path),
            default                     => new DmsException(
                "DMS request failed with status {$status}: " . $e->response->body()
            ),
        };
    }

    // -------------------------------------------------------------------------
    // Upload
    // -------------------------------------------------------------------------

    public function upload(string $path, string $contents, string $visibility = 'private'): array
    {
        try {
            return $this->http()
                ->attach('file', $contents, basename($path))
                ->post('/dms-disk/upload', [
                    'path'       => $path,
                    'disk'       => $this->disk(),
                    'visibility' => $visibility,
                ])
                ->throw()
                ->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw DmsConnectionException::unreachable($this->config['url'], $e->getMessage());
        } catch (RequestException $e) {
            $this->handleRequestException($e, $path);
        }
    }

    public function uploadStream(string $path, mixed $resource, string $visibility = 'private'): array
    {
        try {
            $contents = is_resource($resource) ? stream_get_contents($resource) : $resource;

            return $this->http()
                ->attach('file', $contents, basename($path))
                ->post('/dms-disk/upload', [
                    'path'       => $path,
                    'disk'       => $this->disk(),
                    'visibility' => $visibility,
                ])
                ->throw()
                ->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw DmsConnectionException::unreachable($this->config['url'], $e->getMessage());
        } catch (RequestException $e) {
            $this->handleRequestException($e, $path);
        }
    }

    // -------------------------------------------------------------------------
    // Download
    // -------------------------------------------------------------------------

    public function download(string $path): string
    {
        try {
            return $this->http()
                ->get('/dms-disk/file', ['path' => $path, 'disk' => $this->disk()])
                ->throw()
                ->body();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw DmsConnectionException::unreachable($this->config['url'], $e->getMessage());
        } catch (RequestException $e) {
            $this->handleRequestException($e, $path);
        }
    }

    public function downloadStream(string $path): mixed
    {
        $contents = $this->download($path);
        $stream   = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        return $stream;
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function delete(string $path): void
    {
        try {
            $this->http()
                ->delete('/dms-disk/file?' . http_build_query(['path' => $path, 'disk' => $this->disk()]))
                ->throw();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw DmsConnectionException::unreachable($this->config['url'], $e->getMessage());
        } catch (RequestException $e) {
            $this->handleRequestException($e, $path);
        }
    }

    // -------------------------------------------------------------------------
    // Existence
    // -------------------------------------------------------------------------

    public function exists(string $path): bool
    {
        try {
            return (bool) $this->http()
                ->get('/dms-disk/exists', ['path' => $path, 'disk' => $this->disk()])
                ->throw()
                ->json('exists');
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw DmsConnectionException::unreachable($this->config['url'], $e->getMessage());
        } catch (RequestException $e) {
            $this->handleRequestException($e, $path);
        }
    }

    // -------------------------------------------------------------------------
    // URLs
    // -------------------------------------------------------------------------

    public function url(string $path): string
    {
        try {
            return (string) $this->http()
                ->get('/dms-disk/url', ['path' => $path, 'disk' => $this->disk()])
                ->throw()
                ->json('url');
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw DmsConnectionException::unreachable($this->config['url'], $e->getMessage());
        } catch (RequestException $e) {
            $this->handleRequestException($e, $path);
        }
    }

    public function temporaryUrl(string $path, int $expirySeconds = 3600): array
    {
        try {
            return $this->http()
                ->get('/dms-disk/temp-url', [
                    'path'   => $path,
                    'disk'   => $this->disk(),
                    'expiry' => $expirySeconds,
                ])
                ->throw()
                ->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw DmsConnectionException::unreachable($this->config['url'], $e->getMessage());
        } catch (RequestException $e) {
            $this->handleRequestException($e, $path);
        }
    }

    // -------------------------------------------------------------------------
    // Move / Copy
    // -------------------------------------------------------------------------

    public function move(string $from, string $to): void
    {
        try {
            $this->http()
                ->post('/dms-disk/move', [
                    'from' => $from,
                    'to'   => $to,
                    'disk' => $this->disk(),
                ])
                ->throw();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw DmsConnectionException::unreachable($this->config['url'], $e->getMessage());
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        }
    }

    // -------------------------------------------------------------------------
    // Listing
    // -------------------------------------------------------------------------

    public function listFiles(string $directory = '', bool $recursive = false): array
    {
        try {
            return $this->http()
                ->get('/dms-disk/list', [
                    'directory' => $directory,
                    'recursive' => $recursive ? 'true' : 'false',
                    'disk'      => $this->disk(),
                ])
                ->throw()
                ->json('files', []);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw DmsConnectionException::unreachable($this->config['url'], $e->getMessage());
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        }
    }

    // -------------------------------------------------------------------------
    // Metadata
    // -------------------------------------------------------------------------

    public function metadata(string $path): array
    {
        try {
            return $this->http()
                ->get('/dms-disk/metadata', ['path' => $path, 'disk' => $this->disk()])
                ->throw()
                ->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw DmsConnectionException::unreachable($this->config['url'], $e->getMessage());
        } catch (RequestException $e) {
            $this->handleRequestException($e, $path);
        }
    }

    // -------------------------------------------------------------------------
    // Visibility
    // -------------------------------------------------------------------------

    public function setVisibility(string $path, string $visibility): void
    {
        try {
            $this->http()
                ->post('/dms-disk/visibility', [
                    'path'       => $path,
                    'visibility' => $visibility,
                    'disk'       => $this->disk(),
                ])
                ->throw();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw DmsConnectionException::unreachable($this->config['url'], $e->getMessage());
        } catch (RequestException $e) {
            $this->handleRequestException($e, $path);
        }
    }

    public function visibility(string $path): string
    {
        return $this->metadata($path)['visibility'] ?? 'private';
    }
}