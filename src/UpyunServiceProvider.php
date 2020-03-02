<?php


namespace Vagh\Laravel\Upyun;

use League\Flysystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Vagh\Laravel\Upyun\plugins\GetUrl;
use Vagh\Laravel\Upyun\plugins\PutFile;

class UpYunServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('upyun', function ($app, $config) {
            $filesystem = new Filesystem(new UpYunAdapter($config));
            $filesystem->addPlugin(new GetUrl());
            $filesystem->addPlugin(new PutFile());

            return $filesystem;
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}