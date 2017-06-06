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
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\EncryptException;
use BynqIO\Encryptor\Contracts\Encrypter as EncrypterContract;

class Encrypter implements EncrypterContract
{
    /**
     * The algorithm used for encryption.
     *
     * @var string
     */
    protected $cipher;

    /**
     * Create a new encrypter instance.
     *
     * @param  string  $key
     * @param  string  $cipher
     * @return void
     *
     * @throws \RuntimeException
     */
    public function __construct($cipher = 'AES-256-CBC')
    {
        $this->cipher = $cipher;
    }

    /**
     * Determine if the given key and cipher combination is valid.
     *
     * @param  string  $key
     * @return bool
     */
    public function validKey($key)
    {
        $length = mb_strlen($key, '8bit');

        if (strpos($this->cipher, '128') !== false) {
            return $length == 128/8;
        } else if (strpos($this->cipher, '192') !== false) {
            return $length == 192/8;
        } else if (strpos($this->cipher, '256') !== false) {
            return $length == 256/8;
        } else {
            throw new RuntimeException('The keylength does not match the cipher.');
        }
    }

    /**
     * Encrypt the given value.
     *
     * @param  string $key
     * @param  mixed  $value
     * @param  bool  $serialize
     * @return string
     *
     * @throws \Illuminate\Contracts\Encryption\EncryptException
     */
    public function encrypt($key, $value, $serialize = true)
    {
        $iv = random_bytes(16);

        $this->validKey($key);

        // First we will encrypt the value using OpenSSL. After this is encrypted we
        // will proceed to calculating a MAC for the encrypted value so that this
        // value can be verified later as not having been changed by the users.
        $value = openssl_encrypt($serialize ? serialize($value) : $value, $this->cipher, $key, 0, $iv);

        if ($value === false) {
            throw new EncryptException('Could not encrypt the data.');
        }

        // Once we have the encrypted value we will go ahead base64_encode the input
        // vector and create the MAC for the encrypted value so we can verify its
        // authenticity. Then, we'll JSON encode the data in a "payload" array.
        $mac = $this->hash($key, $iv = base64_encode($iv), $value);

        $json = json_encode(compact('iv', 'value', 'mac'));

        if (!is_string($json)) {
            throw new EncryptException('Could not encrypt the data.');
        }

        return $json;
    }

    /**
     * Encrypt a string without serialization.
     *
     * @param  string $key
     * @param  string  $value
     * @return string
     */
    public function encryptString($key, $value)
    {
        return $this->encrypt($key, $value, false);
    }

    /**
     * Decrypt the given value.
     *
     * @param  string $key
     * @param  mixed  $payload
     * @param  bool  $unserialize
     * @return string
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    public function decrypt($key, $payload, $unserialize = true)
    {
        $payload = $this->getJsonPayload($key, $payload);

        $iv = base64_decode($payload['iv']);

        // Here we will decrypt the value. If we are able to successfully decrypt it
        // we will then unserialize it and return it out to the caller. If we are
        // unable to decrypt this value we will throw out an exception message.
        $decrypted = openssl_decrypt($payload['value'], $this->cipher, $key, 0, $iv);

        if ($decrypted === false) {
            throw new DecryptException('Could not decrypt the data.');
        }

        return $unserialize ? unserialize($decrypted) : $decrypted;
    }

    /**
     * Decrypt the given string without unserialization.
     *
     * @param  string $key
     * @param  string  $payload
     * @return string
     */
    public function decryptString($key, $payload)
    {
        return $this->decrypt($key, $payload, false);
    }

    /**
     * Create a MAC for the given value.
     *
     * @param  string $key
     * @param  string  $iv
     * @param  mixed  $value
     * @return string
     */
    protected function hash($key, $iv, $value)
    {
        return hash_hmac('sha256', $iv.$value, $key);
    }

    /**
     * Get the JSON array from the given payload.
     *
     * @param  string $key
     * @param  string  $payload
     * @return array
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    protected function getJsonPayload($key, $payload)
    {
        $payload = json_decode($payload, true);

        // If the payload is not valid JSON or does not have the proper keys set we will
        // assume it is invalid and bail out of the routine since we will not be able
        // to decrypt the given value. We'll also check the MAC for this encryption.
        if (!$this->validPayload($payload)) {
            throw new DecryptException('The payload is invalid.');
        }

        if (!$this->validMac($key, $payload)) {
            throw new DecryptException('The MAC is invalid.');
        }

        return $payload;
    }

    /**
     * Verify that the encryption payload is valid.
     *
     * @param  mixed  $payload
     * @return bool
     */
    protected function validPayload($payload)
    {
        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }

    /**
     * Determine if the MAC for the given payload is valid.
     *
     * @param  string $key
     * @param  array  $payload
     * @return bool
     */
    protected function validMac($key, array $payload)
    {
        $calculated = $this->calculateMac($key, $payload, $bytes = random_bytes(16));

        return hash_equals(hash_hmac('sha256', $payload['mac'], $bytes, true), $calculated);
    }

    /**
     * Calculate the hash of the given payload.
     *
     * @param  string $key
     * @param  array  $payload
     * @param  string  $bytes
     * @return string
     */
    protected function calculateMac($key, $payload, $bytes)
    {
        return hash_hmac('sha256', $this->hash($key, $payload['iv'], $payload['value']), $bytes, true);
    }
}
