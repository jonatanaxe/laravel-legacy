<?php

use App\Legacy\Billing\InvoiceRecord;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * SIG invoices, as they arrived in the 2017 import.
 *
 * Deterministic (no faker): always the same list, so the numbers on screen
 * match what is said in the demo. Both status formats coexist on purpose:
 * 0/1/2 (SIG) and draft/sent (newer screens). Fictional Brazilian companies
 * - the client's customers keep their real-world names.
 */
class LegacyInvoicesSeeder extends Seeder
{
    public function run()
    {
        InvoiceRecord::query()->delete();

        $customers = [
            ['Golden Wheat Bakery Ltd', '12.345.678/0001-95'],
            ['Torque Auto Repair', '23.456.789/0001-10'],
            ['Bright Smile Dental Clinic', '34.567.890/0002-21'],
            ['Cornerstone Builders', '45.678.901/0001-32'],
            ['Little Minds School', '56.789.012/0001-43'],
            ['John Perry (sole trader)', '123.456.789-09'],
        ];

        // [customer, status, days ago, items]
        $rows = [
            [0, '1', 85, [['Tax consulting', 4, 180.00]]],
            [1, '1', 78, [['System maintenance', 10, 95.00], ['Travel', 2, 60.00]]],
            [2, '2', 70, [['Team training', 1, 1200.00]]],
            [3, '1', 64, [['Technical hour', 16, 150.00]]],
            [4, '0', 55, [['Annual license', 1, 2400.00]]],
            [0, 'sent', 47, [['Tax consulting', 3, 180.00]]],
            [5, '1', 40, [['Remote support', 5, 80.00]]],
            [1, 'sent', 33, [['System maintenance', 8, 95.00]]],
            [3, 'draft', 25, [['Technical hour', 12, 150.00], ['Technical report', 1, 450.00]]],
            [2, '1', 18, [['Team training', 1, 1200.00], ['Training material', 20, 35.00]]],
            [4, 'sent', 11, [['Remote support', 6, 80.00]]],
            [0, '2', 6, [['Tax consulting', 2, 180.00]]],
            [5, 'draft', 2, [['Remote support', 3, 80.00]]],
            [3, '1', 1, [['Technical hour', 20, 150.00]]],
        ];

        $number = 1040;

        foreach ($rows as $row) {
            list($customer, $status, $daysAgo, $items) = $row;

            $payload = array_map(function ($item) {
                return ['description' => $item[0], 'qty' => $item[1], 'price' => $item[2]];
            }, $items);

            $total = array_sum(array_map(function ($item) {
                return $item[1] * $item[2];
            }, $items));

            $issued = Carbon::today()->subDays($daysAgo)->setTime(10, 0);

            InvoiceRecord::create([
                'number' => ++$number,
                'customer_name' => $customers[$customer][0],
                'customer_document' => $customers[$customer][1],
                'status' => $status,
                'total' => $total,
                'payload' => json_encode($payload),
                'issued_at' => $issued,
                'cancelled_at' => in_array($status, ['2'], true) ? $issued->copy()->addDays(2) : null,
            ]);
        }
    }
}
