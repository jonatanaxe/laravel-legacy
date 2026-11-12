<?php

namespace Tests\Unit\Legacy;

use App\Legacy\Billing\InvoiceRecord;
use App\Legacy\Reports\BillingReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingReportTest extends TestCase
{
    use RefreshDatabase;

    private function record($status, $total, $payload = null)
    {
        static $number = 100;

        return InvoiceRecord::create(array(
            'number' => ++$number,
            'customer_name' => 'ACME',
            'customer_document' => '12345678000195',
            'status' => $status,
            'total' => $total,
            'payload' => $payload,
            'issued_at' => '2026-11-01 10:00:00',
        ));
    }

    public function test_summary_by_status_sorted_by_amount()
    {
        $this->record('1', 300);
        $this->record('1', 200);
        $this->record('0', 100);
        $this->record('2', 50);

        $this->assertSame(array(
            array('status' => 'Issued', 'qty' => 2, 'amount' => 500.0),
            array('status' => 'Issued', 'qty' => 1, 'amount' => 100.0), // a draft, see StatusLabel
            array('status' => 'Cancelled', 'qty' => 1, 'amount' => 50.0),
        ), (new BillingReport())->byStatus());
    }

    public function test_dates_become_carbon()
    {
        $this->assertInstanceOf(Carbon::class, $this->record('1', 10)->fresh()->issued_at);
    }

    public function test_record_becomes_an_invoice_with_items()
    {
        $payload = json_encode(array(
            array('description' => 'Technical hour', 'qty' => 2, 'price' => 150),
        ));

        $invoice = $this->record('1', 300, $payload)->fresh()->toInvoice();

        $this->assertSame(300.0, $invoice->total());
        $this->assertSame('ACME', $invoice->customer->name);
        $this->assertNotNull($invoice->legacy_id);
    }

    public function test_record_without_items_becomes_an_empty_invoice()
    {
        $invoice = $this->record('0', 0)->fresh()->toInvoice();

        $this->assertCount(0, $invoice->items);
        // round() returns a float even without items.
        $this->assertSame(0.0, $invoice->total());
    }
}
