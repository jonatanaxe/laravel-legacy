<?php

namespace App\Legacy\Reports;

use App\Legacy\Billing\StatusLabel;
use Illuminate\Support\Facades\DB;

/**
 * Monthly billing report by status ("Billing > Summary" screen).
 */
class BillingReport
{
    public function byStatus()
    {
        $rows = DB::table('legacy_invoices')
            ->select('status', DB::raw('COUNT(*) as qty'), DB::raw('SUM(total) as amount'))
            ->groupBy('status')
            ->orderByRaw(DB::raw('SUM(total) DESC'))
            ->get();

        $out = array();

        foreach ($rows as $row) {
            $row = (array) $row;

            $out[] = array(
                'status' => StatusLabel::forStatus(array_get($row, 'status')),
                'qty' => (int) array_get($row, 'qty'),
                'amount' => (float) array_get($row, 'amount'),
            );
        }

        return $out;
    }
}
