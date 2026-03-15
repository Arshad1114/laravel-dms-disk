<?php

namespace Arshad1114\DmsDisk;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class DmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/dms-disk.php',
            'dms-disk'
        );
    }

    public function boot(): void
    {
        // Publish config so the consuming app can override via .env
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/dms-disk.php' => config_path('dms-disk.php'),
            ], 'dms-disk-config');
        }

        // Extend the Storage system with our custom 'dms' driver
        Storage::extend('dms', function (Application $app, array $diskConfig) {

            // Merge package config with disk-level config from filesystems.php
            // Disk-level config wins, allowing multiple DMS disks with different URLs
            $resolved = array_merge(
                config('dms-disk', []),
                array_filter($diskConfig, fn($v) => ! is_null($v))
            );

            $client  = new DmsClient($resolved);
            $adapter = new DmsDriver($client);

            return new FilesystemAdapter(
                new Filesystem($adapter, $resolved),
                $adapter,
                $resolved
            );
        });
    }
}
