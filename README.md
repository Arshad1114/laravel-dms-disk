# Laravel DMS Disk

A Laravel Filesystem driver for Document Management Systems (DMS) via a REST API. Integrates seamlessly with Laravel's `Storage` facade using Flysystem v3.

## Requirements

- PHP 8.1+
- Laravel 10 or 11

## Installation

```bash
composer require arshadnoor/laravel-dms-disk
```

The service provider is auto-discovered via Laravel's package discovery.

Publish the config file:

```bash
php artisan vendor:publish --tag=dms-disk-config
```

## Configuration

Add the following to your `.env` file:

```env
DMS_BASE_URL=https://dms.example.com/api/v1
DMS_API_TOKEN=your-secret-token
DMS_TIMEOUT=30
```

Add a new disk to `config/filesystems.php`:

```php
'disks' => [
    // ...

    'dms' => [
        'driver'    => 'dms',
        'base_url'  => env('DMS_BASE_URL'),
        'api_token' => env('DMS_API_TOKEN'),
        'timeout'   => env('DMS_TIMEOUT', 30),
    ],
],
```

## Usage

Use the `Storage` facade as you would with any other Laravel disk:

```php
use Illuminate\Support\Facades\Storage;

// Write a file
Storage::disk('dms')->put('documents/report.pdf', $contents);

// Read a file
$contents = Storage::disk('dms')->get('documents/report.pdf');

// Check existence
if (Storage::disk('dms')->exists('documents/report.pdf')) {
    // ...
}

// Delete a file
Storage::disk('dms')->delete('documents/report.pdf');

// List files
$files = Storage::disk('dms')->files('documents');

// Copy / move
Storage::disk('dms')->copy('documents/a.pdf', 'archive/a.pdf');
Storage::disk('dms')->move('documents/draft.pdf', 'documents/final.pdf');
```

## Expected DMS API Contract

The driver expects the following REST endpoints on the configured `base_url`:

| Method   | Endpoint                    | Description                        |
|----------|-----------------------------|------------------------------------|
| `GET`    | `/files/{path}`             | Download file contents             |
| `PUT`    | `/files/{path}`             | Upload / overwrite file            |
| `DELETE` | `/files/{path}`             | Delete a file                      |
| `HEAD`   | `/files/{path}`             | Check file existence               |
| `GET`    | `/files/{path}/metadata`    | Get file metadata (size, mime, ts) |
| `GET`    | `/files?path=&recursive=`   | List directory contents            |
| `POST`   | `/files/copy`               | Copy a file (`{from, to}` body)    |
| `POST`   | `/files/move`               | Move a file (`{from, to}` body)    |

All requests are authenticated via a `Bearer` token (`Authorization: Bearer <api_token>`).

### Metadata response shape

```json
{
    "size": 1024,
    "mime_type": "application/pdf",
    "last_modified": 1700000000
}
```

### List contents response shape

```json
{
    "files": [
        {
            "path": "documents/report.pdf",
            "size": 1024,
            "mime_type": "application/pdf",
            "last_modified": 1700000000
        }
    ]
}
```

## Exceptions

| Exception                  | When thrown                              |
|----------------------------|------------------------------------------|
| `DmsAuthException`         | API returns 401 or 403                   |
| `DmsFileNotFoundException`  | API returns 404 on read/metadata         |
| `DmsConnectionException`   | Network or connection failure            |
| `DmsException`             | Any other DMS API error                  |

All exceptions extend `DmsException`, which extends `RuntimeException`.

## Testing

```bash
composer test
```

## License

MIT
