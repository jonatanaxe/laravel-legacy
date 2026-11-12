<?php

namespace Tests\Http;

/**
 * /health is what the orchestrator asks: do the dependencies answer?
 * 200 when everything passes; 503 when any check fails. The body is real
 * JSON, so it is validated field by field - not with grep.
 */
class HealthTest extends HttpTestCase
{
    public function test_health_checks_dependencies()
    {
        $response = $this->guest()->get('/health');

        $this->assertSame(200, $response->getStatusCode(), '/health must answer 200');

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('ok', $payload['status'] ?? null, 'overall status');

        foreach (['database', 'cache', 'storage'] as $probe) {
            $this->assertSame(true, $payload['checks'][$probe]['ok'] ?? null, "check $probe");
        }
    }

    public function test_health_does_not_leak_secrets()
    {
        $body = (string) $this->guest()->get('/health')->getBody();

        $this->assertStringNotContainsString('APP_KEY', $body);
        $this->assertStringNotContainsString('DB_PASSWORD', $body);
        $this->assertStringNotContainsString('password', strtolower($body));
    }
}
