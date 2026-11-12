<?php

namespace App\Legacy\Billing;

class Customer
{
    public $name;
    public $document;

    public function __construct($name, $document)
    {
        $this->name = $name;
        // Keep only the digits: the SIG accepted "12.345.678/0001-95".
        $this->document = preg_replace('/\D/', '', $document);
    }

    public function isCompany()
    {
        return strlen($this->document) == 14;
    }

    /**
     * CNPJ branch (positions 9 to 12). A short or invalid document falls back
     * to the head office, which is what the city hall accepts as default.
     */
    public function branch()
    {
        $branch = substr($this->document, 8, 4);

        return $branch === false ? '0001' : $branch;
    }
}
