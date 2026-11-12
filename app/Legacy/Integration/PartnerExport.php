<?php

namespace App\Legacy\Integration;

use App\Legacy\Billing\Invoice;

/**
 * Export to the accounting partner (2008 integration).
 * Their system only reads WDDX and CSV in ISO-8859-1.
 */
class PartnerExport
{
    public function toWddx(Invoice $invoice)
    {
        return wddx_serialize_value(array(
            'number' => $invoice->code(),
            'customer' => $this->latin1($invoice->customer->name),
            'total' => $invoice->total(),
        ));
    }

    public function csvLine(Invoice $invoice)
    {
        $columns = array(
            $invoice->code(),
            $invoice->customer->document,
            number_format($invoice->total(), 2, ',', ''),
        );

        return implode($columns, ';');
    }

    public function latin1($text)
    {
        return utf8_decode($text);
    }
}
