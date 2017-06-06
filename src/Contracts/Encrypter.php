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

namespace BynqIO\Encryptor\Contracts;

interface Encrypter
{
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
    public function encrypt($key, $value, $serialize = true);

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
    public function decrypt($key, $payload, $unserialize = true);
}
