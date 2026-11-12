<?php

namespace Tests\Http;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Http\Message\ResponseInterface;

/**
 * A browser session against the running server.
 *
 * Each instance has its own cookie jar, so "ana" and "bruno" never
 * share a session - that is what makes the IDOR test real, rather than two
 * anonymous requests.
 *
 * Requests go over the network (the container's nginx, or `artisan serve` in CI).
 * There is no kernel bootstrap here on purpose: inside PHPUnit Laravel
 * disables VerifyCsrfToken when it detects `runningUnitTests()`, so a 419
 * would never show up. CSRF, cookies and TLS are proven by the real
 * server.
 */
class Browser
{
    /** @var Client */
    private $client;

    /** @var CookieJar */
    private $jar;

    /** @var ResponseInterface|null */
    private $last;

    public function __construct($base, array $options = [])
    {
        $this->jar = new CookieJar();

        $defaults = [
            'base_uri' => rtrim($base, '/') . '/',
            // 4xx/5xx are expected responses, not exceptions.
            'http_errors' => false,
            // 302 is what is being asserted; following it would hide the status.
            'allow_redirects' => false,
            'cookies' => $this->jar,
            // Self-signed certificate of the local environment.
            'verify' => false,
            'timeout' => 30,
        ];

        $this->client = new Client(array_merge($defaults, $options));
    }

    public function request($method, $uri, array $options = [])
    {
        return $this->last = $this->client->request($method, $uri, $options);
    }

    public function status($method, $uri, array $options = [])
    {
        return $this->request($method, $uri, $options)->getStatusCode();
    }

    public function get($uri, array $options = [])
    {
        return $this->request('GET', $uri, $options);
    }

    public function body(ResponseInterface $response = null)
    {
        $response = $response ?: $this->last;

        return $response ? (string) $response->getBody() : '';
    }

    /**
     * Value of an <input> on the page, read through the DOM - not with grep.
     * Used for _token (CSRF) and for the optimistic lock's `version` field.
     */
    public function field($uri, $name)
    {
        return $this->fieldOf($this->get($uri), $name);
    }

    public function fieldOf(ResponseInterface $response, $name)
    {
        $xpath = $this->xpath($response);
        $node = $xpath->query('//input[@name="' . $name . '"]')->item(0);

        return $node ? $node->getAttribute('value') : null;
    }

    public function token($uri)
    {
        $token = $this->field($uri, '_token');

        if ($token === null || $token === '') {
            throw new \RuntimeException("page $uri did not include a _token field");
        }

        return $token;
    }

    /**
     * The page's destinations: <a> hrefs and <form> actions.
     *
     * Both matter: the view uses route(), so URLs are absolute and the test
     * extracts the path by pattern. Looking only at <a> would miss actions the
     * product exposes as forms (marking a reminder as read, for example).
     *
     * @return string[]
     */
    public function links($uri)
    {
        $xpath = $this->xpath($this->get($uri));
        $targets = [];

        foreach ($xpath->query('//a[@href]') as $anchor) {
            $targets[] = $anchor->getAttribute('href');
        }

        foreach ($xpath->query('//form[@action]') as $form) {
            $targets[] = $form->getAttribute('action');
        }

        return $targets;
    }

    /**
     * First value captured by $pattern in the page's links (null = not found).
     * Works for numeric ids and for UUID keys (notifications).
     */
    public function firstMatch($uri, $pattern)
    {
        foreach ($this->links($uri) as $href) {
            if (preg_match($pattern, $href, $match)) {
                return $match[1];
            }
        }

        return null;
    }

    /**
     * Same search, for numeric ids (0 = not found).
     */
    public function firstId($uri, $pattern)
    {
        $value = $this->firstMatch($uri, $pattern);

        return $value === null ? 0 : (int) $value;
    }

    /**
     * Id taken from a redirect's Location. More reliable than scraping the
     * list: it depends neither on ordering nor on prior database state.
     */
    public function idFromLocation(ResponseInterface $response, $pattern)
    {
        $location = $response->getHeaderLine('Location');

        return preg_match($pattern, $location, $match) ? (int) $match[1] : 0;
    }

    public function login($email, $password = 'secret123')
    {
        return $this->status('POST', '/login', [
            'form_params' => [
                '_token' => $this->token('/login'),
                'email' => $email,
                'password' => $password,
            ],
        ]);
    }

    /**
     * POST with _method: reproduces the HTML form (method spoofing), which is
     * how the browser sends PUT/PATCH/DELETE.
     */
    public function submit($method, $uri, array $fields)
    {
        return $this->request('POST', $uri, [
            'form_params' => array_merge(['_method' => $method], $fields),
        ]);
    }

    private function xpath(ResponseInterface $response)
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        // The prefix declares UTF-8 to libxml's old parser.
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $this->body($response));

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }
}
