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

use RuntimeException;
use Ramsey\Uuid\Uuid;
// use Illuminate\Http\File;
use InvalidArgumentException;
// use Illuminate\Http\UploadedFile;
use League\Flysystem\AdapterInterface;
use League\Flysystem\FilesystemInterface;
// use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\FileNotFoundException;
// use League\Flysystem\Adapter\Local as LocalAdapter;
use Illuminate\Filesystem\FilesystemAdapter;
use BynqIO\Encryptor\Exception\NotSupportedException;
// use Illuminate\Contracts\Encryption\Encrypter;
// use Illuminate\Contracts\Filesystem\Cloud as CloudFilesystemContract;
// use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Contracts\Filesystem\FileNotFoundException as ContractFileNotFoundException;

class StorageAdapter //implements FilesystemContract, CloudFilesystemContract
{
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
    public function __construct(FilesystemAdapter $driver, $encrypter)
    {
        $this->driver = $driver;
        $this->encrypter = $encrypter;
    }

    /**
     * Get the contents of a file.
     *
     * @param  string  $path
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function get($path, $key)
    {
        try {
            $object = json_decode($this->driver->read($path));
            $contents = base64_decode($object->contents);

            if ($object->encrypted) {
                return $this->encrypter->decryptString($key, $contents);
            } else {
                return $contents;
            }
        } catch (FileNotFoundException $e) {
            throw new ContractFileNotFoundException($path, $e->getCode(), $e);
        }
    }

    /**
     * Write the contents of a file.
     *
     * @param  string  $path
     * @param  string|resource  $contents
     * @param  array  $options
     * @return bool
     */
    public function put($path, $contents, $key = null, $options = [])
    {
        if (is_string($options)) {
            $options = ['visibility' => $options];
        }

        $size = strlen($contents);
        $mime = 'application/pdf';//TODO
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
    public function putAuto($path, $contents, $key = null, $options = [])
    {
        $path = $path.'/'.Uuid::uuid4()->toString();
        $result = $this->put($path, $contents, $key, $options = []);
        return $result ? $path : false;
    }

    /**
     * Store the uploaded file on the disk.
     *
     * @param  string  $path
     * @param  \Illuminate\Http\File|\Illuminate\Http\UploadedFile  $file
     * @param  array  $options
     * @return string|false
     */
    public function putFile($path, $file, $options = [])
    {
        return $this->putFileAs($path, $file, $file->hashName(), $options);
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
    public function putFileAs($path, $file, $name, $options = [])
    {
        $stream = fopen($file->getRealPath(), 'r+');

        // Next, we will format the path of the file and store the file using a stream since
        // they provide better performance than alternatives. Once we write the file this
        // stream will get closed automatically by us so the developer doesn't have to.
        $result = $this->put(
            $path = trim($path.'/'.$name, '/'), $stream, $options
        );

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $result ? $path : false;
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
     * Get the Flysystem driver.
     *
     * @return \League\Flysystem\FilesystemInterface
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