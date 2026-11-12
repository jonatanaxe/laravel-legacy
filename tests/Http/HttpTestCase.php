<?php

namespace Tests\Http;

use PHPUnit\Framework\TestCase;

/**
 * Base of the HTTP suite.
 *
 * Unlike tests/Feature (SQLite :memory:, CSRF disabled), everything here
 * happens against the running server. The price is depending on a live
 * environment; the gain is proving what only exists at the edge: 419,
 * cookies, throttling, headers, TLS and IDOR between separate sessions.
 *
 * Variables:
 *   HTTP_BASE     target URL (default https://laravel-legacy.dev)
 *   HTTP_RESOLVE  0 turns off --resolve 127.0.0.1 (CI uses `artisan serve`)
 *
 * Requires a seeded database: accounts and the project come from UsersSeeder/ProjectsSeeder.
 */
abstract class HttpTestCase extends TestCase
{
    const PASSWORD = 'secret123';

    /** @var array<string, Browser> */
    private $sessions = [];

    protected function base()
    {
        return getenv('HTTP_BASE') ?: 'https://laravel-legacy.dev';
    }

    protected function isHttps()
    {
        return parse_url($this->base(), PHP_URL_SCHEME) === 'https';
    }

    protected function browser()
    {
        $options = [];
        $host = parse_url($this->base(), PHP_URL_HOST);

        // By default no IP is pinned: inside the container madock's DNS already
        // resolves the domain, and in CI the target is 127.0.0.1. HTTP_RESOLVE=1
        // exists for running PHP on the host, where the domain doesn't resolve.
        if ($this->isHttps() && getenv('HTTP_RESOLVE') === '1') {
            $options['curl'] = [CURLOPT_RESOLVE => [$host . ':443:127.0.0.1']];
        }

        return new Browser($this->base(), $options);
    }

    protected function guest()
    {
        return $this->browser();
    }

    /**
     * Authenticated session, memoized per test: one test's cookie jar never
     * leaks into the next.
     */
    protected function session($email)
    {
        if (!isset($this->sessions[$email])) {
            $browser = $this->browser();
            $status = $browser->login($email, self::PASSWORD);

            $this->assertSame(302, $status, "login as $email should redirect (302)");

            $this->sessions[$email] = $browser;
        }

        return $this->sessions[$email];
    }

    protected function ana()
    {
        return $this->session('ana@example.com');
    }

    protected function bruno()
    {
        return $this->session('bruno@example.com');
    }

    protected function carla()
    {
        return $this->session('carla@example.com');
    }

    /**
     * Deterministic token from UsersSeeder: sha256(email | 'lab-migration').
     * So the API can discover ids without depending on HTML ordering.
     */
    protected function tokenFor($email)
    {
        return hash('sha256', $email . '|lab-migration');
    }

    protected function anaToken()
    {
        return $this->tokenFor('ana@example.com');
    }

    protected function api($method, $uri, $token = null, array $options = [])
    {
        $headers = ['Accept' => 'application/json'];

        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $options['headers'] = array_merge(
            $headers,
            isset($options['headers']) ? $options['headers'] : []
        );

        return $this->guest()->request($method, $uri, $options);
    }

    protected function apiJson($uri, $token = null)
    {
        $response = $this->api('GET', $uri, $token);

        $this->assertSame(200, $response->getStatusCode(), "GET $uri should respond 200");

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * Ana's PERSONAL task, used by the IDOR tests: bruno is a member of her
     * project, so it has to be a resource outside the project.
     */
    protected function personalTaskId()
    {
        $payload = $this->apiJson('/api/tasks?q=policies', $this->anaToken());

        $this->assertNotEmpty($payload['data'], 'seed has no personal "policies" task');
        $this->assertEmpty(
            $payload['data'][0]['project'] ?? null,
            'the IDOR task must be personal (no project)'
        );

        return (int) $payload['data'][0]['id'];
    }

    protected function projectId()
    {
        $id = $this->ana()->firstId('/projects', '#/projects/(\d+)#');

        $this->assertGreaterThan(0, $id, 'seed has no project visible to ana');

        return $id;
    }

    protected function projectTaskId()
    {
        $payload = $this->apiJson('/api/tasks?q=Inventory', $this->anaToken());

        $this->assertNotEmpty($payload['data'], 'seed has no project task');
        $this->assertNotEmpty(
            $payload['data'][0]['project'] ?? null,
            'the role task must belong to the project'
        );

        return (int) $payload['data'][0]['id'];
    }

    /**
     * Temporary file on the disk of whoever runs the test. Tests remove it at
     * the end; if a test fails midway, /tmp takes care of it on the next reboot.
     */
    protected function tempFile($contents, $suffix = '.txt')
    {
        $path = sys_get_temp_dir() . '/http-suite-' . uniqid() . $suffix;
        file_put_contents($path, $contents);

        return $path;
    }

    protected function removeFile($path)
    {
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Multipart upload: _token + file, like the browser's form.
     *
     * $tokenUri is a GET page containing the _token field (the upload
     * endpoint only accepts POST, so the token comes from the form pointing to it).
     *
     * @return int status HTTP
     */
    protected function upload(Browser $browser, $uri, $tokenUri, $path, $filename, $field = 'file')
    {
        $handle = fopen($path, 'r');

        try {
            return $browser->status('POST', $uri, [
                'multipart' => [
                    ['name' => '_token', 'contents' => $browser->token($tokenUri)],
                    ['name' => $field, 'contents' => $handle, 'filename' => $filename],
                ],
            ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
