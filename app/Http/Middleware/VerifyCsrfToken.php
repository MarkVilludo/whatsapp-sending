<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * Infobip (and other providers) cannot send a Laravel CSRF token with webhook POSTs.
     * Routes under routes/api.php skip CSRF by default; add here if you expose the webhook on web routes too.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/infobip/sms/webhook',
    ];
}
