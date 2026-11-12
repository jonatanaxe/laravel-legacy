<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array
     */
    protected $except = [
        // Theme preference, written by the browser (lab.js). It carries no
        // sensitive data and the layout only accepts whitelisted values.
        'brand',
    ];
}
