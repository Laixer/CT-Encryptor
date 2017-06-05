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

namespace BynqIO\Encryptor\Facades;

use Illuminate\Support\Facades\Facade;

class Encryptor extends Facade {

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'files.encryptor';
    }
}
