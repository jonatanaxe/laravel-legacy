<?php

namespace App\Legacy\Integration;

/**
 * Payment gateway client, from before the project used Guzzle.
 */
class GatewayClient
{
    private $handle;
    private $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->handle = curl_init();

        if (!is_resource($this->handle)) {
            throw new \RuntimeException('Could not initialize cURL');
        }

        curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->handle, CURLOPT_TIMEOUT, 10);
    }

    public function url($path, array $query = array())
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        return $query ? $url . '?' . http_build_query($query) : $url;
    }

    public function isOpen()
    {
        return is_resource($this->handle);
    }

    public function close()
    {
        if (is_resource($this->handle)) {
            curl_close($this->handle);
        }
    }
}
