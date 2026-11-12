<?php

namespace App\Flow;

/**
 * Chart geometry in plain PHP.
 *
 * There is no chart library, canvas or JS: the SVG is generated on the server
 * and arrives ready in the HTML. What this buys in this project:
 *
 *  - works with the current CSP (`script-src 'self'`), no exception needed;
 *  - zero build step;
 *  - the chart is testable: coordinates are a pure function of the data, so
 *    "the series starts at zero and never decreases" is assertable without a browser.
 *
 * Visualization choices (not personal taste):
 *  - stacked area for the CFD: each band's height is the amount of work in
 *    that stage, and the band's thickness over time is the WIP. It is the
 *    chart's canonical reading (PMBOK 7 / Wikipedia), not an
 *    invention;
 *  - columns for throughput: length is the most precise encoding there is
 *    for quantity (NN/g, pre-attentive processing);
 *  - no pie, donut, gauge or 3D: angle and area are poor for comparing
 *    values, and the dashboard loses legibility.
 */
class Chart
{
    /** Inner padding of the drawing, in px. */
    const PAD_TOP = 18;
    const PAD_RIGHT = 14;
    const PAD_BOTTOM = 28;
    const PAD_LEFT = 38;

    /**
     * Stacked area (used by the CFD).
     *
     * @param string[] $labels one label per X-axis point
     * @param array    $series ['stage' => [n, n, ...]] in stacking order
     * @param int      $width
     * @param int      $height
     *
     * @return array{width:int,height:int,plot:array,max:int,bands:array,grid:array,ticks:array}
     */
    public static function stacked(array $labels, array $series, $width, $height)
    {
        $count = count($labels);
        $plot = self::plot($width, $height);

        if ($count === 0) {
            return self::emptyChart($width, $height);
        }

        // A single-point series draws no area: repeat the point so it has width.
        // Better than returning an empty SVG and confusing the reader.
        if ($count === 1) {
            $labels = [$labels[0], $labels[0]];
            foreach ($series as $key => $values) {
                $series[$key] = [$values[0], $values[0]];
            }
            $count = 2;
        }

        $totals = array_fill(0, $count, 0);
        foreach ($series as $values) {
            foreach ($values as $i => $value) {
                $totals[$i] += $value;
            }
        }

        $scale = self::niceScale(max(1, max($totals)));

        $stepX = $plot['w'] / ($count - 1);

        $x = function ($i) use ($plot, $stepX) {
            return $plot['x'] + $i * $stepX;
        };
        $y = function ($value) use ($plot, $scale) {
            return $plot['y'] + $plot['h'] - ($value / $scale['top']) * $plot['h'];
        };

        // Each band is a polygon: top edge (left->right) and bottom edge
        // (right->left). The previous cumulative value is the base of the next
        // band - that is what does the stacking.
        $bands = [];
        $base = array_fill(0, $count, 0);
        $running = array_fill(0, $count, 0);

        foreach ($series as $key => $values) {
            $top = [];
            foreach ($values as $i => $value) {
                $running[$i] += $value;
                $top[$i] = $running[$i];
            }

            $points = [];
            $edge = [];
            for ($i = 0; $i < $count; $i++) {
                $points[] = self::pair($x($i), $y($top[$i]));
                $edge[] = self::pair($x($i), $y($top[$i]));
            }
            for ($i = $count - 1; $i >= 0; $i--) {
                $points[] = self::pair($x($i), $y($base[$i]));
            }

            $bands[$key] = [
                'key' => $key,
                'points' => implode(' ', $points),
                // Top edge: drawn as a hairline to separate the bands without needing
                // a strong color on each one.
                'edge' => implode(' ', $edge),
                'last' => $running[$count - 1],
                'peak' => max($top),
            ];

            $base = $top;
        }

        return [
            'width' => $width,
            'height' => $height,
            'plot' => $plot,
            'max' => $scale['top'],
            'bands' => $bands,
            'grid' => self::grid($y, $scale),
            'ticks' => self::xTicks($labels, $x, $count),
        ];
    }

    /**
     * Columns (used by throughput).
     *
     * @param string[] $labels
     * @param int[]    $values
     *
     * @return array
     */
    public static function columns(array $labels, array $values, $width, $height)
    {
        $count = count($labels);
        $plot = self::plot($width, $height);

        if ($count === 0) {
            return self::emptyChart($width, $height);
        }

        $scale = self::niceScale(max(1, max($values)));
        $slot = $plot['w'] / $count;
        $barWidth = $slot * 0.52;

        $y = function ($value) use ($plot, $scale) {
            return $plot['y'] + $plot['h'] - ($value / $scale['top']) * $plot['h'];
        };

        $bars = [];
        $ticks = [];

        for ($i = 0; $i < $count; $i++) {
            $cx = $plot['x'] + $slot * $i + $slot / 2;
            $top = $y($values[$i]);

            $bars[] = [
                'x' => round($cx - $barWidth / 2, 1),
                'y' => round($top, 1),
                'w' => round($barWidth, 1),
                // Height 0 becomes 0 in the SVG (nothing shows); better than a 1px ghost
                // bar on a week with no delivery.
                'h' => round($plot['y'] + $plot['h'] - $top, 1),
                'value' => $values[$i],
                'label' => $labels[$i],
                'cx' => round($cx, 1),
                'base' => $plot['y'] + $plot['h'],
            ];

            $ticks[] = ['x' => round($cx, 1), 'label' => $labels[$i]];
        }

        return [
            'width' => $width,
            'height' => $height,
            'plot' => $plot,
            'max' => $scale['top'],
            'bars' => $bars,
            'grid' => self::grid($y, $scale),
            // With 8 weeks every label fits; past that the axis becomes noise.
            'ticks' => $count <= 8 ? $ticks : self::xTicks($labels, function ($i) use ($slot, $plot) {
                return $plot['x'] + $slot * $i + $slot / 2;
            }, $count),
        ];
    }

    /**
     * "Round" scale: the top of the axis is a multiple of 1/2/5 x a power of
     * 10, so ticks are readable numbers (0, 10, 20...) and not 0, 7.3...
     */
    private static function niceScale($max)
    {
        $target = $max / 3;
        $magnitude = pow(10, floor(log10(max($target, 0.1))));
        $normal = $target / $magnitude;

        if ($normal <= 1) {
            $nice = 1;
        } elseif ($normal <= 2) {
            $nice = 2;
        } elseif ($normal <= 5) {
            $nice = 5;
        } else {
            $nice = 10;
        }

        $step = $nice * $magnitude;

        return ['step' => $step, 'top' => ceil($max / $step) * $step];
    }

    private static function grid(\Closure $y, array $scale)
    {
        $grid = [];

        for ($value = 0; $value <= $scale['top'] + 0.0001; $value += $scale['step']) {
            $grid[] = [
                'value' => (int) round($value),
                'y' => round($y($value), 1),
            ];
        }

        return $grid;
    }

    /**
     * At most 5 labels on the X axis: more than that becomes a wall of text.
     */
    private static function xTicks(array $labels, \Closure $x, $count)
    {
        $wanted = min($count, 5);
        $seen = [];
        $ticks = [];

        for ($k = 0; $k < $wanted; $k++) {
            $i = $wanted === 1 ? 0 : (int) round($k * ($count - 1) / ($wanted - 1));

            if (isset($seen[$i])) {
                continue;
            }

            $seen[$i] = true;
            $ticks[] = ['x' => round($x($i), 1), 'label' => $labels[$i]];
        }

        return $ticks;
    }

    /**
     * Horizontal bars (WIP aging). Length = quantity; the label sits outside
     * the drawing area so it doesn't compete with the bar.
     *
     * @param array $items [['label'=>string, 'value'=>int, 'warn'=>bool], ...]
     */
    public static function hbars(array $items, $width, $rowHeight = 28, $labelWidth = 104)
    {
        $valueWidth = 44;
        $track = $width - $labelWidth - $valueWidth;
        $max = 1;

        foreach ($items as $item) {
            $max = max($max, $item['value']);
        }

        $rows = [];
        $i = 0;

        foreach ($items as $item) {
            $barWidth = round($track * ($item['value'] / $max), 1);

            $rows[] = [
                'label' => $item['label'],
                'value' => $item['value'],
                'y' => $i * $rowHeight + 5,
                'mid' => round($i * $rowHeight + $rowHeight / 2, 1),
                'track_w' => $track,
                'w' => $barWidth,
                'label_y' => round($i * $rowHeight + $rowHeight / 2 + 4, 1),
                'value_x' => round($labelWidth + $barWidth + 8, 1),
                'warn' => !empty($item['warn']),
            ];

            $i++;
        }

        return [
            'width' => $width,
            'height' => max(1, count($items) * $rowHeight),
            'rows' => $rows,
            'label_width' => $labelWidth,
            'track_w' => $track,
            'bar_h' => $rowHeight - 10,
        ];
    }


    /**
     * Single stacked bar (project progress).
     *
     * Unlike the CFD there is no time axis here: it is a snapshot of today's
     * work. Each segment's width = that stage's share of the total, so the bar
     * answers "how much has left" at a glance.
     *
     * @param array $segments [['key'=>string, 'label'=>string, 'value'=>int], ...]
     */
    public static function stack(array $segments, $width, $height = 14)
    {
        $total = 0;

        foreach ($segments as $segment) {
            $total += (int) $segment['value'];
        }

        $x = 0;
        $out = [];

        foreach ($segments as $segment) {
            $value = (int) $segment['value'];
            $w = $total > 0 ? round($width * ($value / $total), 1) : 0;

            $out[] = [
                'key' => $segment['key'],
                'label' => isset($segment['label']) ? $segment['label'] : $segment['key'],
                'value' => $value,
                'x' => round($x, 1),
                'w' => $w,
                'pct' => $total > 0 ? (int) round($value / $total * 100) : 0,
            ];

            $x += $w;
        }

        return [
            'width' => $width,
            'height' => $height,
            'total' => $total,
            'segments' => $out,
            'empty' => $total === 0,
        ];
    }

    private static function plot($width, $height)
    {
        return [
            'x' => self::PAD_LEFT,
            'y' => self::PAD_TOP,
            'w' => $width - self::PAD_LEFT - self::PAD_RIGHT,
            'h' => $height - self::PAD_TOP - self::PAD_BOTTOM,
        ];
    }

    private static function pair($x, $y)
    {
        return round($x, 1) . ',' . round($y, 1);
    }

    private static function emptyChart($width, $height)
    {
        return [
            'width' => $width,
            'height' => $height,
            'plot' => self::plot($width, $height),
            'max' => 1,
            'bands' => [],
            'bars' => [],
            'grid' => [],
            'ticks' => [],
        ];
    }
}
