<?php

namespace Tests\Http;

/**
 * Flow and project overview at the HTTP edge.
 *
 * The kernel test proves the math; this one proves the page arrives built
 * for whoever has a session, that guests are stopped on the server and that the
 * chart travels as SVG - no chart library from a CDN, which the current CSP wouldn't allow.
 */
class FlowTest extends HttpTestCase
{
    public function test_guest_cannot_open_the_flow()
    {
        $response = $this->guest()->get('/flow');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->getHeaderLine('location'));
    }

    public function test_flow_delivers_ready_svg_without_an_external_library()
    {
        $response = $this->ana()->get('/flow');

        $this->assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        $this->assertStringContainsString('Workflow', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('Cumulative flow diagram', $html);

        // Chart made on the server: no chart library loaded from a CDN
        // (the CSP would block it, but the test catches it before the browser).
        $this->assertStringNotContainsString('chart.js', $html);
        $this->assertStringNotContainsString('cdn.', $html);
    }

    public function test_project_opens_on_the_overview_with_timeline()
    {
        $response = $this->ana()->get('/projects/' . $this->projectId());

        $this->assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        $this->assertStringContainsString('Timeline', $html);
        $this->assertStringContainsString('% done', $html);
        // The page doesn't list everything: it shows what is open now and the team.
        $this->assertStringContainsString('Open now', $html);
        $this->assertStringContainsString('<svg', $html);
    }

    /**
     * A viewer follows the project (view policy), they just don't write. The
     * overview is exactly the page for a client who wants to see progress.
     */
    public function test_viewer_opens_the_overview()
    {
        $response = $this->carla()->get('/projects/' . $this->projectId());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Timeline', (string) $response->getBody());
    }
}
