<?php

namespace App\Http\Controllers;

use App\Flow\Chart;
use App\Flow\FlowMetrics;
use Illuminate\Http\Request;

/**
 * A process view, not a task view.
 *
 * The list answers "what do I do now"; this page answers "how is the work
 * flowing". Different questions, so different pages - stacking both on one
 * screen would make both worse.
 *
 * Every drawing is server-generated SVG: no chart library, no JS, no build.
 * Metrics come from FlowMetrics; geometry from Chart. The controller only
 * connects the two.
 */
class FlowController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        // The window comes from the query string and is clamped on the server.
        $days = FlowMetrics::clampDays($request->query('days'));

        $flow = (new FlowMetrics($request->user(), $days))->build();

        // Geometry separate from calculation: the same series can be redrawn
        // at another width without touching the metrics.
        $flow['cfd']['chart'] = Chart::stacked(
            $flow['cfd']['labels'],
            $flow['cfd']['series'],
            960,
            300
        );

        $flow['throughput']['chart'] = Chart::columns(
            $flow['throughput']['labels'],
            $flow['throughput']['values'],
            440,
            200
        );

        $buckets = [];
        foreach ($flow['aging']['buckets'] as $bucket) {
            $buckets[] = [
                'label' => $bucket['label'],
                'value' => $bucket['count'],
                // Past 30 days it stops being "in progress" and becomes a
                // risk: the bar changes color, not the text.
                'warn' => $bucket['limit'] === null || $bucket['limit'] > 30,
            ];
        }

        $flow['aging']['chart'] = Chart::hbars($buckets, 440, 28);

        return view('flow.index', [
            'flow' => $flow,
            'days' => $days,
        ]);
    }
}
