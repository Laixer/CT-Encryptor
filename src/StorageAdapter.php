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

use finfo;
use RuntimeException;
use Ramsey\Uuid\Uuid;
use League\Flysystem\FilesystemInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use BynqIO\Encryptor\Contracts\Encrypter;
use BynqIO\Encryptor\Contracts\Filesystem as FilesystemContract;
use BynqIO\Encryptor\Exception\NotSupportedException;

class StorageAdapter implements FilesystemContract
{
    /**
     * File info provider.
     *
     * @var \finfo
     */
    protected $finfo;

    /**
     * The Flysystem filesystem implementation.
     *
     * @var \Illuminate\Filesystem\FilesystemAdapter
     */
    protected $driver;

    /**
     * The Flysystem filesystem implementation.
     *
     * @var \Illuminate\Contracts\Encryption\Encrypter
     */
    protected $encrypter;

    /**
     * Encryptor instance.
     *
     * @param  \Illuminate\Filesystem\FilesystemAdapter  $driver
     * @return void
     */
    public function __construct(FilesystemAdapter $driver, Encrypter $encrypter)
    {
        $this->finfo = new finfo(FILEINFO_MIME_TYPE);
        $this->driver = $driver;
        $this->encrypter = $encrypter;
    }

    /**
     * Generate unique name for file.
     *
     * @param  string  $path
     * @return string
     */
    private function generateName($path, $ext)
    {
        return $path.'/'.Uuid::uuid4()->toString().'.'.$ext;
    }

    /**
     * Get the contents of a file.
     *
     * @param  string  $path
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function get($path, $key = null)
    {
        $object = json_decode($this->driver->read($path));
        $contents = base64_decode($object->contents);

        if ($object->encrypted) {
            return $this->encrypter->decryptString($key, $contents);
        } else {
            return $contents;
        }
    }

    /**
     * Write the contents of a file.
     *
     * @param  string  $path
     * @param  string  $contents
     * @param  string  $key
     * @param  array  $options
     * @return bool
     */
    public function put($path, $contents, $key = null, $options = [])
    {
        if (is_string($options)) {
            $options = ['visibility' => $options];
        }

        if (is_resource($contents)) {
            $contents = stream_get_contents($contents);
        }

        $mime = $this->finfo->buffer($contents);
        $size = strlen($contents);

        $encrypted = false;
        if (!is_null($key)) {
            $contents = $this->encrypter->encryptString($key, $contents);
            $encrypted = true;
        }

        $contents = base64_encode($contents);
        $object = compact('size','mime','contents','encrypted');

        return $this->driver->put($path, json_encode($object), $options);
    }

    /**
     * Write the contents of a file.
     *
     * @param  string  $path
     * @param  string|resource  $contents
     * @param  array  $options
     * @return bool
     */
    public function putAuto($path, $ext, $contents, $key = null, $options = [])
    {
        $name = $this->generateName($path, $ext);
        $result = $this->put($name, $contents, $key, $options);
        return $result ? $name : false;
    }

    /**
     * Store the uploaded file on the disk.
     *
     * @param  string  $path
     * @param  \Illuminate\Http\File|\Illuminate\Http\UploadedFile  $file
     * @param  array  $options
     * @return string|false
     */
    public function putFile($path, $file, $key = null, $options = [])
    {
        return $this->putFileAs($path, $file, $file->hashName(), $key, $options);
    }

    /**
     * Store the uploaded file on the disk with a given name.
     *
     * @param  string  $path
     * @param  \Illuminate\Http\File|\Illuminate\Http\UploadedFile  $file
     * @param  string  $name
     * @param  array  $options
     * @return string|false
     */
    public function putFileAs($path, $file, $name, $key, $options = [])
    {
        $stream = fopen($file->getRealPath(), 'r+');

        // Next, we will format the path of the file and store the file using a stream since
        // they provide better performance than alternatives. Once we write the file this
        // stream will get closed automatically by us so the developer doesn't have to.
        $result = $this->put($path = trim($path.'/'.$name, '/'), $stream, $key, $options);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $result ? $path : false;
    }

    /**
     * Get the contents as base64 string.
     *
     * @param  string  $path
     * @return string
     */
    public function base64($path)
    {
        $object = json_decode($this->driver->read($path));
        return 'data:' . $object->mime . ';base64,' . $object->contents;
    }

    /**
     * Prepend to a file.
     *
     * @param  string  $path
     * @param  string  $data
     * @param  string  $separator
     * @return int
     */
    public function prepend($path, $data, $separator = PHP_EOL)
    {
        throw new NotSupportedException('Operation cannot operate on encrypted data');
    }

    /**
     * Append to a file.
     *
     * @param  string  $path
     * @param  string  $data
     * @param  string  $separator
     * @return int
     */
    public function append($path, $data, $separator = PHP_EOL)
    {
        throw new NotSupportedException('Operation cannot operate on encrypted data');
    }

    /**
     * Get the file size of a given file.
     *
     * @param  string  $path
     * @return int
     */
    public function size($path)
    {
        $object = json_decode($this->driver->read($path));
        return $object->size;
    }

    /**
     * Get the mime-type of a given file.
     *
     * @param  string  $path
     * @return string|false
     */
    public function mimeType($path)
    {
        $object = json_decode($this->driver->read($path));
        return $object->mime;
    }

    /**
     * Get the filesystem driver.
     *
     * @return \Illuminate\Filesystem\FilesystemAdapter
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * Pass dynamic methods call onto Flysystem.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, array $parameters)
    {
        return call_user_func_array([$this->driver, $method], $parameters);
    }
}