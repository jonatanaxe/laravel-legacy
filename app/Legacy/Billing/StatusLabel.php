<?php

namespace App\Legacy\Billing;

class StatusLabel
{
    /**
     * Label printed on the billing report.
     */
    public static function forStatus($status)
    {
        return $status == 0 ? 'Draft' : $status == 1 ? 'Issued' : 'Cancelled';
    }
}
