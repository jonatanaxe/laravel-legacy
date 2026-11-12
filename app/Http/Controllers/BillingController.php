<?php

namespace App\Http\Controllers;

use App\Legacy\Billing\InvoiceRecord;
use App\Legacy\Reports\BillingReport;
use Illuminate\Support\Facades\DB;

/**
 * Billing: the screen of the legacy SIG module (app/Legacy).
 *
 * New code NEXT TO the legacy, without touching it: the correct summary is
 * computed here, and the old report (BillingReport) is shown as is,
 * including the wrong draft label - frozen behavior under test
 * (tests/Unit/Legacy), not a slip of this screen.
 *
 * Read only. SIG invoices have no per-row owner: they are company data.
 */
class BillingController extends Controller
{
    public function index()
    {
        // One query: status mixes the SIG world (0/1/2) and the new one (draft/sent).
        $rows = DB::table('legacy_invoices')
            ->select('status', DB::raw('COUNT(*) as qty'), DB::raw('SUM(total) as amount'))
            ->groupBy('status')
            ->get();

        $summary = [];
        foreach (InvoiceRecord::STATUS_NAMES as $name) {
            $summary[$name] = ['qty' => 0, 'amount' => 0.0];
        }

        foreach ($rows as $row) {
            $name = InvoiceRecord::statusNameFor($row->status);
            $summary[$name]['qty'] += (int) $row->qty;
            $summary[$name]['amount'] += (float) $row->amount;
        }

        return view('billing.index', [
            'summary' => $summary,
            'invoices' => InvoiceRecord::orderByDesc('issued_at')->orderByDesc('id')->paginate(12),
            'legacyReport' => (new BillingReport())->byStatus(),
        ]);
    }
}
