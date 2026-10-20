<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
            str_starts_with(config('app.url'), 'https://')
        ) {
            URL::forceScheme('https');
        }
        if (class_exists(\Illuminate\Foundation\Console\ServeCommand::class)) {
            \Illuminate\Foundation\Console\ServeCommand::$passthroughVariables = array_unique(array_merge(
                \Illuminate\Foundation\Console\ServeCommand::$passthroughVariables,
                [
                    'SystemRoot',
                    'systemroot',
                    'SYSTEMROOT',
                    'SystemDrive',
                    'systemdrive',
                    'SYSTEMDRIVE',
                    'windir',
                    'WINDIR',
                    'TEMP',
                    'temp',
                    'TMP',
                    'tmp',
                    'APPDATA',
                    'LOCALAPPDATA',
                    'ComSpec',
                    'COMSPEC',
                    'HOMEDRIVE',
                    'HOMEPATH',
                ]
            ));
        }
    }
}
