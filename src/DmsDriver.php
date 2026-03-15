<?php

namespace Arshad1114\DmsDisk;

use Arshad1114\DmsDisk\Exceptions\DmsException;
use Arshad1114\DmsDisk\Exceptions\DmsFileNotFoundException;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;

class DmsDriver implements FilesystemAdapter
{
    public function __construct(private readonly DmsClient $client) {}

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $visibility = $config->get(Config::OPTION_VISIBILITY, Visibility::PRIVATE);
            $this->client->upload($path, $contents, $visibility);
        } catch (DmsException $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            $visibility = $config->get(Config::OPTION_VISIBILITY, Visibility::PRIVATE);
            $this->client->uploadStream($path, $contents, $visibility);
        } catch (DmsException $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    public function read(string $path): string
    {
        try {
            return $this->client->download($path);
        } catch (DmsFileNotFoundException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        } catch (DmsException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            return $this->client->downloadStream($path);
        } catch (DmsFileNotFoundException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        } catch (DmsException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function delete(string $path): void
    {
        try {
            $this->client->delete($path);
        } catch (DmsFileNotFoundException) {
            // Flysystem contract: deleting a non-existent file is a no-op
        } catch (DmsException $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        // Delete all files under the directory path
        try {
            $files = $this->client->listFiles($path, true);
            foreach ($files as $file) {
                $this->client->delete($file);
            }
        } catch (DmsException $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    // -------------------------------------------------------------------------
    // Directory (no-op — the DMS is path-based, not directory-based)
    // -------------------------------------------------------------------------

    public function createDirectory(string $path, Config $config): void
    {
        // Path-based storage — directories are implicit, nothing to create
    }

    // -------------------------------------------------------------------------
    // Existence
    // -------------------------------------------------------------------------

    public function fileExists(string $path): bool
    {
        try {
            return $this->client->exists($path);
        } catch (DmsException $e) {
            throw UnableToCheckFileExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            // A directory "exists" if it contains at least one file
            return count($this->client->listFiles($path)) > 0;
        } catch (DmsException) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Move / Copy
    // -------------------------------------------------------------------------

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->client->move($source, $destination);
        } catch (DmsException $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            // Read source + write to destination — DMS contract has no native copy
            $contents = $this->client->download($source);
            $visibility = $config->get(Config::OPTION_VISIBILITY, Visibility::PRIVATE);
            $this->client->upload($destination, $contents, $visibility);
        } catch (DmsException $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    // -------------------------------------------------------------------------
    // Listing
    // -------------------------------------------------------------------------

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $files = $this->client->listFiles($path, $deep);

            foreach ($files as $filePath) {
                yield new FileAttributes($filePath);
            }
        } catch (DmsException $e) {
            // Yield nothing on error — Flysystem treats this as an empty directory
        }
    }

    // -------------------------------------------------------------------------
    // Metadata
    // -------------------------------------------------------------------------

    public function fileSize(string $path): FileAttributes
    {
        try {
            $meta = $this->client->metadata($path);
            return new FileAttributes($path, $meta['size'] ?? null);
        } catch (DmsException $e) {
            throw UnableToRetrieveMetadata::fileSize($path, $e->getMessage(), $e);
        }
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $meta = $this->client->metadata($path);
            return new FileAttributes($path, null, null, null, $meta['mime_type'] ?? null);
        } catch (DmsException $e) {
            throw UnableToRetrieveMetadata::mimeType($path, $e->getMessage(), $e);
        }
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            $meta = $this->client->metadata($path);
            return new FileAttributes($path, null, null, $meta['last_modified'] ?? null);
        } catch (DmsException $e) {
            throw UnableToRetrieveMetadata::lastModified($path, $e->getMessage(), $e);
        }
    }

    // -------------------------------------------------------------------------
    // Visibility
    // -------------------------------------------------------------------------

    public function visibility(string $path): FileAttributes
    {
        try {
            $visibility = $this->client->visibility($path);
            return new FileAttributes($path, null, $visibility);
        } catch (DmsException $e) {
            throw UnableToRetrieveMetadata::visibility($path, $e->getMessage(), $e);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        try {
            $this->client->setVisibility($path, $visibility);
        } catch (DmsException $e) {
            throw UnableToSetVisibility::atLocation($path, $e->getMessage(), $e);
        }
    }
}