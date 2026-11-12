<?php

namespace App\Legacy\Billing;

/**
 * SIG issuing rules, in the order the city hall used to reject them.
 */
class InvoiceValidator
{
    /** Accepted service codes (city list) + exemption. */
    const SERVICE_CODES = array('0107', '0108', '1401', 'EXEMPT');

    private $errors = array();

    public function isDraft(Invoice $invoice)
    {
        return $invoice->status == 0;
    }

    public function validate(Invoice $invoice, array $meta = array())
    {
        $this->errors = array();

        if (!array_key_exists('number', $invoice) || $invoice->number == '') {
            $this->errors[] = 'number required';
        }

        // The old form sends 0 when the field is left blank.
        $code = array_get($meta, 'service_code', 0);

        if (!in_array($code, self::SERVICE_CODES)) {
            $this->errors[] = 'invalid service code';
        }

        foreach ($invoice->items as $item) {
            if (!is_numeric($item['qty'])) {
                $this->errors[] = 'invalid quantity: ' . $item['description'];
            }
        }

        return count($this->errors) == 0;
    }

    public function errors()
    {
        return $this->errors;
    }
}
