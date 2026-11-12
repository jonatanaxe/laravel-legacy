<?php

namespace App\Legacy\Billing;

use Illuminate\Database\Eloquent\Model;

/**
 * A row of the legacy_invoices table (imported from the SIG in 2017).
 */
class InvoiceRecord extends Model
{
    protected $table = 'legacy_invoices';

    protected $guarded = array();

    protected $dates = array('issued_at', 'cancelled_at');

    protected $casts = array(
        'total' => 'float',
    );

    /*
     * --- New code (2026), written next to the legacy ----------------------
     * Explicit, strict mapping of the two status worlds. It exists so the
     * Billing screen does not depend on the SIG's StatusLabel, which prints
     * drafts as "Issued".
     */
    const STATUS_NAMES = ['draft' => 'Draft', 'sent' => 'Issued', 'cancelled' => 'Cancelled', 'unknown' => 'Unknown'];

    const STATUS_MAP = ['0' => 'draft', 'draft' => 'draft', '1' => 'sent', 'sent' => 'sent', '2' => 'cancelled', 'cancelled' => 'cancelled'];

    public static function statusNameFor($status)
    {
        $key = self::STATUS_MAP[(string) $status] ?? 'unknown';

        return self::STATUS_NAMES[$key];
    }

    public function statusName()
    {
        return self::statusNameFor($this->status);
    }

    public function code()
    {
        return 'NF-' . str_pad($this->number, 6, '0', STR_PAD_LEFT);
    }

    public function toInvoice()
    {
        $customer = new Customer($this->customer_name, $this->customer_document);

        $invoice = new Invoice(array(
            'number' => $this->number,
            'status' => $this->status,
            'legacy_id' => $this->id,
        ), $customer);

        $items = json_decode($this->payload, true);

        if (is_array($items)) {
            foreach ($items as $item) {
                $invoice->addItem($item['description'], $item['qty'], $item['price']);
            }
        }

        return $invoice;
    }
}
