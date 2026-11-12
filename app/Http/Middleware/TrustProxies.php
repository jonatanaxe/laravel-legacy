<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Fideloper\Proxy\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Under madock TLS terminates at the proxy (aruntime/nginx) and the
     * request reaches PHP over http, with X-Forwarded-Proto: https. Without
     * trusting the proxy, $request->isSecure() and request()->url() answer
     * http, and the paginator (which builds the path from the Request, not
     * the UrlGenerator) emits "http://" links inside an https page -> mixed
     * content.
     *
     * '*' because the only hop is this local stack's nginx. In production,
     * use the load balancer's IP/CIDR: trusting everyone lets a client that
     * reaches the port directly forge the header.
     *
     * @var array|string
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
