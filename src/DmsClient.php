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
                    return $e instanceof \Illuminate\Http\Client\ConnectionException;
                }
            )
            ->acceptJson();
    }

    private function disk(): ?string
    {
        $disk = $this->config['disk'] ?? null;
        return empty($disk) ? null : $disk;
    }

    private function withDisk(array $params): array
    {
        if ($this->disk()) {
            $params['disk'] = $this->disk();
        }
        return $params;
    }

    private function withDiskQuery(array $params): string
    {
        return http_build_query($this->withDisk($params));
    }

    // -------------------------------------------------------------------------
    // Exception mapper
    // -------------------------------------------------------------------------

    private function handleRequestException(RequestException $e, string $path = ''): never
    {
        $status = $e->response->status();

        throw match (true) {
            $status === 401 => DmsAuthException::invalidToken(),
            $status === 404 => DmsFileNotFoundException::atPath($path),
            default         => new DmsException(
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
                ->post('/dms-disk/upload', $this->withDisk([
                    'path'       => $path,
                    'visibility' => $visibility,
                ]))
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
                ->post('/dms-disk/upload', $this->withDisk([
                    'path'       => $path,
                    'visibility' => $visibility,
                ]))
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
                ->get('/dms-disk/file', $this->withDisk(['path' => $path]))
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
                ->delete('/dms-disk/file?' . $this->withDiskQuery(['path' => $path]))
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
                ->get('/dms-disk/exists', $this->withDisk(['path' => $path]))
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
                ->get('/dms-disk/url', $this->withDisk(['path' => $path]))
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
                ->get('/dms-disk/temp-url', $this->withDisk([
                    'path'   => $path,
                    'expiry' => $expirySeconds,
                ]))
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
                ->post('/dms-disk/move', $this->withDisk([
                    'from' => $from,
                    'to'   => $to,
                ]))
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
                ->get('/dms-disk/list', $this->withDisk([
                    'directory' => $directory,
                    'recursive' => $recursive ? 'true' : 'false',
                ]))
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
                ->get('/dms-disk/metadata', $this->withDisk(['path' => $path]))
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
                ->post('/dms-disk/visibility', $this->withDisk([
                    'path'       => $path,
                    'visibility' => $visibility,
                ]))
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