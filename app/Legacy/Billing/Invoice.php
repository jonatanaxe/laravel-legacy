<?php

namespace App\Legacy\Billing;

use App\Legacy\Support\Text;

/**
 * SIG service invoice.
 *
 * status: the SIG stored an integer (0 draft, 1 issued, 2 cancelled).
 * Newer screens started writing text ('draft', 'sent'). Both coexist
 * in the legacy_invoices table.
 */
class Invoice
{
    public $number;
    public $status = 0;
    public $customer;
    public $items;

    public function __construct(array $data = array(), Customer $customer = null)
    {
        $this->items = new ItemCollection();
        $this->customer = $customer;

        // Any extra SIG column (notes, legacy_id...) becomes an attribute.
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public function addItem($description, $qty = 1, $price)
    {
        $this->items[] = array(
            'description' => $description,
            'qty' => $qty,
            'price' => $price,
        );

        return $this;
    }

    public function total()
    {
        $total = 0;

        foreach ($this->items as $item) {
            $total += $item['qty'] * $item['price'];
        }

        return round($total, 2);
    }

    public function code()
    {
        return Text::code('NF', $this->number);
    }
}
