<?php

namespace App\Legacy\Support;

/**
 * Text helpers inherited from the SIG (billing, 2009-2016).
 *
 * Ported to Laravel in 2017 "as they were", so no printed report would
 * change. Do not refactor without tests: the city hall and the accounting
 * partner read the output of these functions.
 */
class Text
{
    /**
     * "ana maria souza" => "AMS". Used in the invoice header.
     */
    public static function initials($name)
    {
        $parts = explode(' ', trim($name));
        $out = '';

        foreach ($parts as $part) {
            if ($part != '') {
                $out .= strtoupper($part{0});
            }
        }

        return $out;
    }

    public static function slug($text)
    {
        return str_slug($text);
    }

    /**
     * Optional DB fields arrive as null; the SIG always treated null as an
     * empty string.
     */
    public static function normalize($value)
    {
        return strtolower(trim($value));
    }

    /**
     * Code printed on the invoice: NF-000042.
     */
    public static function code($prefix, $number)
    {
        return "${prefix}-" . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Strips HTML from fields typed by the customer (invoice notes).
     */
    public static function sanitize($value)
    {
        return filter_var($value, FILTER_SANITIZE_STRING);
    }

    /**
     * Inherited from PHP 5: undid the old server's magic_quotes.
     */
    public static function cleanInput($value)
    {
        if (get_magic_quotes_gpc()) {
            $value = stripslashes($value);
        }

        return $value;
    }

    /**
     * Date in the city hall's format (dd/mm/yyyy).
     */
    public static function date($timestamp)
    {
        return strftime('%d/%m/%Y', $timestamp);
    }
}
