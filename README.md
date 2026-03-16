# Laravel DMS Disk

[![Latest Version on Packagist](https://img.shields.io/packagist/v/arshad1114/laravel-dms-disk.svg?style=flat-square)](https://packagist.org/packages/arshad1114/laravel-dms-disk)
[![Total Downloads](https://img.shields.io/packagist/dt/arshad1114/laravel-dms-disk.svg?style=flat-square)](https://packagist.org/packages/arshad1114/laravel-dms-disk)
[![License](https://img.shields.io/packagist/l/arshad1114/laravel-dms-disk.svg?style=flat-square)](https://packagist.org/packages/arshad1114/laravel-dms-disk)
[![Try in Codespaces](https://github.com/codespaces/badge.svg)](https://codespaces.new/arshad1114/laravel-dms-disk-demo)

A custom Laravel filesystem disk driver that lets any Laravel microservice store, retrieve and manage files on a remote Document Management Service (DMS) using the **native `Storage` facade** — no custom HTTP calls, no helper functions, no boilerplate.

## Try it online — no install needed

Click the button above to launch a live demo in your browser using GitHub Codespaces. Both services start automatically — no setup required.

Once the Codespace loads:
```bash
bash .devcontainer/start.sh
```

Open a new terminal:
```bash
cd consumer-service && php artisan tinker
```

Then try:
```php
Storage::disk('dms')->put('test/hello.txt', 'Hello World!');
Storage::disk('dms')->get('test/hello.txt');
Storage::disk('dms')->delete('test/hello.txt');
```

## The problem

In a microservice architecture, when a service needs to store files on a dedicated DMS service, developers typically write custom HTTP calls in every service:
```php
// ❌ what developers do today — repeated in every service
$response = Http::attach('file', $contents, 'invoice.pdf')
    ->post('https://dms.internal/upload', ['path' => 'invoices/001.pdf']);
```

## The solution

Install this package and use the native `Storage` facade as you always have:
```php
// ✅ with laravel-dms-disk
Storage::disk('dms')->put('invoices/001.pdf', $contents);
Storage::disk('dms')->get('invoices/001.pdf');
Storage::disk('dms')->delete('invoices/001.pdf');
```

The HTTP transport is completely invisible.

## How it works
```
Consumer service                         DMS service
────────────────                         ───────────────────────────
Storage::disk('dms')->put(...)           Receives the HTTP request
       │                                 Calls Storage::put(...)
       │           HTTPS                 using its own
       └──────────────────────────────►  filesystems.php config
```

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation
```bash
composer require arshad1114/laravel-dms-disk
```

The `DmsServiceProvider` is auto-discovered — no manual registration needed.

Publish the config:
```bash
php artisan vendor:publish --tag=dms-disk-config
```

## Configuration

Add to your `.env`:
```env
DMS_URL=https://your-dms-service.internal
DMS_TOKEN=your-strong-secret-token
```

Add the `dms` disk to `config/filesystems.php`:
```php
'disks' => [
    // ... your existing disks

    'dms' => [
        'driver' => 'dms',
    ],
],
```

### Full config reference

All options in `config/dms-disk.php`:

| Key | Env variable | Default | Description |
|---|---|---|---|
| `url` | `DMS_URL` | `''` | Base URL of your DMS service |
| `token` | `DMS_TOKEN` | `''` | Bearer token for authentication |
| `timeout` | `DMS_TIMEOUT` | `30` | HTTP timeout in seconds |
| `retry` | `DMS_RETRY` | `3` | Retry attempts on connection failure |
| `retry_delay` | `DMS_RETRY_DELAY` | `200` | Milliseconds between retries |

### Multiple DMS disks

You can point multiple disks to different DMS services:
```php
'disks' => [
    'dms' => [
        'driver' => 'dms',
        'url'    => env('DMS_URL'),
        'token'  => env('DMS_TOKEN'),
    ],
    'dms-archive' => [
        'driver' => 'dms',
        'url'    => env('DMS_ARCHIVE_URL'),
        'token'  => env('DMS_ARCHIVE_TOKEN'),
    ],
],
```

## Usage

### Upload a file
```php
// From a string
Storage::disk('dms')->put('invoices/001.pdf', $pdfContents);

// From an uploaded file in a controller
$request->file('document')->store('documents', 'dms');

// With a custom filename
$request->file('document')->storeAs('documents', 'invoice-001.pdf', 'dms');

// As public visibility
Storage::disk('dms')->put('avatars/user-1.jpg', $imageContents, 'public');
```

### Download a file
```php
// Get file contents as string
$contents = Storage::disk('dms')->get('invoices/001.pdf');

// Stream download directly to browser
return Storage::disk('dms')->download('invoices/001.pdf');

// Stream with custom filename
return Storage::disk('dms')->download('invoices/001.pdf', 'my-invoice.pdf');
```

### Check existence
```php
if (Storage::disk('dms')->exists('invoices/001.pdf')) {
    // file exists
}

if (Storage::disk('dms')->missing('invoices/001.pdf')) {
    // file does not exist
}
```

### Delete a file
```php
Storage::disk('dms')->delete('invoices/001.pdf');
```

### Move and copy
```php
// Move (rename)
Storage::disk('dms')->move('old/path.pdf', 'new/path.pdf');

// Copy
Storage::disk('dms')->copy('original.pdf', 'copy.pdf');
```

### List files
```php
// Files in a directory
$files = Storage::disk('dms')->files('invoices');

// Files recursively
$files = Storage::disk('dms')->allFiles('invoices');
```

### File metadata
```php
$size      = Storage::disk('dms')->size('invoices/001.pdf');
$mime      = Storage::disk('dms')->mimeType('invoices/001.pdf');
$timestamp = Storage::disk('dms')->lastModified('invoices/001.pdf');
```

### URLs
```php
// Public URL
$url = Storage::disk('dms')->url('avatars/user-1.jpg');

// Temporary signed URL
$url = Storage::disk('dms')->temporaryUrl('invoices/001.pdf', now()->addHour());
```

### Visibility
```php
Storage::disk('dms')->setVisibility('avatars/user-1.jpg', 'public');
Storage::disk('dms')->setVisibility('invoices/001.pdf', 'private');

$visibility = Storage::disk('dms')->visibility('avatars/user-1.jpg');
// returns 'public' or 'private'
```

## Troubleshooting

### 401 Unauthorized
`DMS_TOKEN` in the consumer does not match `DMS_SERVER_TOKEN` in the DMS service. Make sure both values are identical.

### Driver [dms] not supported
The `dms` disk is missing from `config/filesystems.php`. Add it as shown in the configuration section above.

### Connection refused / timeout
`DMS_URL` is wrong or the DMS service is not running. Double check the URL and port.

### Routes not found on DMS side
Run `php artisan route:clear` on the DMS service and check `php artisan route:list --path=dms-disk`.

## DMS server packages

Your DMS service can be written in any language that implements the API contract. Official server packages:

| Framework | Package |
|---|---|
| Laravel | [arshad1114/laravel-dms-disk-server](https://github.com/arshad1114/laravel-dms-disk-server) |
| Node.js | Coming soon |

## Contributing

Contributions are welcome. Please:

1. Fork the repo
2. Create a feature branch: `git checkout -b feat/your-feature`
3. Write tests for your change
4. Make sure all tests pass: `./vendor/bin/phpunit`
5. Open a pull request

## License

MIT — see [LICENSE](LICENSE) file.