<?php

/**
 * Copyright (C) 2017 Bynq.io B.V.
 * All Rights Reserved
 *
 * This file is part of the Encryptor project.
 *
 * Content can not be copied and/or distributed without the express
 * permission of the author.
 *
 * @package  Encryptor
 * @author   Yorick de Wid <y.dewid@calculatietool.com>
 */

namespace BynqIO\Encryptor;

use Exception;
use Illuminate\Support\ServiceProvider;

class EncryptorServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @throws \Exception
     * @return void
     */
    public function register()
    {
        $this->registerManager();

        //
    }

    /**
     * Register the filesystem manager.
     *
     * @return void
     */
    protected function registerManager()
    {
        $this->app->singleton('files.encryptor', function ($app) {
            $driver = $app->make('filesystem')->disk();
            return new StorageAdapter($driver, new Encrypter);
        });
    }
}
