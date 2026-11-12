<?php

namespace Tests\Unit\Legacy;

use App\Legacy\Billing\Customer;
use App\Legacy\Billing\Invoice;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    private function invoice()
    {
        $customer = new Customer('Zoë Smith', '12.345.678/0001-95');

        return (new Invoice(array('number' => 42, 'notes' => 'paid in cash'), $customer))
            ->addItem('Technical hour', 2, 150.0)
            ->addItem('Travel', 1, 80);
    }

    public function test_total_adds_up_the_items()
    {
        $this->assertSame(380.0, $this->invoice()->total());
    }

    public function test_extra_column_becomes_an_attribute()
    {
        $this->assertSame('paid in cash', $this->invoice()->notes);
    }

    public function test_items_count_and_survive_serialization()
    {
        $items = $this->invoice()->items;

        $this->assertCount(2, $items);
        $this->assertCount(2, unserialize(serialize($items)));
        $this->assertSame('Travel', $items[1]['description']);
    }

    public function test_printed_code()
    {
        $this->assertSame('NF-000042', $this->invoice()->code());
    }

    public function test_customer_keeps_only_digits_and_finds_the_branch()
    {
        $customer = new Customer('ACME', '12.345.678/0001-95');

        $this->assertSame('12345678000195', $customer->document);
        $this->assertTrue($customer->isCompany());
        $this->assertSame('0001', $customer->branch());
    }

    public function test_short_document_falls_back_to_head_office()
    {
        $this->assertSame('0001', (new Customer('X', '123'))->branch());
    }
}
