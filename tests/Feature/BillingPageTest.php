<?php

namespace Tests\Feature;

use App\Legacy\Billing\InvoiceRecord;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPageTest extends TestCase
{
    use RefreshDatabase;

    private function invoice($number, $status, $total)
    {
        return InvoiceRecord::create([
            'number' => $number,
            'customer_name' => 'Customer ' . $number,
            'customer_document' => '12345678000195',
            'status' => $status,
            'total' => $total,
            'issued_at' => '2026-11-01 10:00:00',
        ]);
    }

    public function test_guest_goes_to_login()
    {
        $this->get('/billing')->assertRedirect('/login');
    }

    public function test_summary_merges_both_status_formats()
    {
        $this->invoice(1, '1', 100);
        $this->invoice(2, 'sent', 50);
        $this->invoice(3, '0', 30);
        $this->invoice(4, 'draft', 20);
        $this->invoice(5, '2', 10);

        $this->actingAs(factory(User::class)->create())->get('/billing')
            ->assertStatus(200)
            ->assertSee('NF-000001')
            ->assertSee('R$ 150.00')   // issued: '1' + 'sent'
            ->assertSee('R$ 50.00')    // drafts: '0' + 'draft'
            ->assertSee('SIG report');
    }

    public function test_empty_screen_explains_how_to_populate()
    {
        $this->actingAs(factory(User::class)->create())->get('/billing')
            ->assertStatus(200)
            ->assertSee('No invoices imported');
    }

    public function test_new_status_mapping_is_strict()
    {
        $this->assertSame('Draft', InvoiceRecord::statusNameFor('0'));
        $this->assertSame('Draft', InvoiceRecord::statusNameFor('draft'));
        $this->assertSame('Issued', InvoiceRecord::statusNameFor('sent'));
        $this->assertSame('Cancelled', InvoiceRecord::statusNameFor(2));
        // The legacy would treat 'sent' == 0 as a draft; here it doesn't.
        $this->assertSame('Unknown', InvoiceRecord::statusNameFor('anything'));
    }
}
