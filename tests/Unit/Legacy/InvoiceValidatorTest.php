<?php

namespace Tests\Unit\Legacy;

use App\Legacy\Billing\Customer;
use App\Legacy\Billing\Invoice;
use App\Legacy\Billing\InvoiceValidator;
use App\Legacy\Billing\StatusLabel;
use Tests\TestCase;

/**
 * Some tests here freeze ODD behavior on purpose. Fixing the oddity is a
 * separate task, with an owner and a heads-up to whoever uses the output.
 */
class InvoiceValidatorTest extends TestCase
{
    private function invoice(array $data = array('number' => 7))
    {
        return (new Invoice($data, new Customer('ACME', '12345678000195')))
            ->addItem('Consulting', 1, 100);
    }

    public function test_draft()
    {
        $validator = new InvoiceValidator();

        $this->assertTrue($validator->isDraft($this->invoice(array('status' => 0))));
        $this->assertFalse($validator->isDraft($this->invoice(array('status' => 1))));
    }

    public function test_text_status_is_treated_as_draft()
    {
        // Odd, but it is what happens today: 'sent' == 0.
        $this->assertTrue((new InvoiceValidator())->isDraft($this->invoice(array('status' => 'sent'))));
    }

    public function test_valid_invoice_with_a_service_code()
    {
        $validator = new InvoiceValidator();

        $this->assertTrue($validator->validate($this->invoice(), array('service_code' => '0107')));
        $this->assertSame(array(), $validator->errors());
    }

    public function test_blank_code_passes()
    {
        // The form sends 0; today 0 matches 'EXEMPT' and the invoice passes.
        $this->assertTrue((new InvoiceValidator())->validate($this->invoice()));
    }

    public function test_unknown_code_is_rejected()
    {
        $validator = new InvoiceValidator();

        $this->assertFalse($validator->validate($this->invoice(), array('service_code' => '9999')));
        $this->assertSame(array('invalid service code'), $validator->errors());
    }

    public function test_number_is_required()
    {
        $validator = new InvoiceValidator();

        $validator->validate($this->invoice(array('number' => '')), array('service_code' => '0107'));

        $this->assertSame(array('number required'), $validator->errors());
    }

    public function test_quantity_with_trailing_csv_space_is_invalid()
    {
        $validator = new InvoiceValidator();
        $invoice = $this->invoice()->addItem('Overtime', '2 ', 50);

        $this->assertFalse($validator->validate($invoice, array('service_code' => '0107')));
        $this->assertSame(array('invalid quantity: Overtime'), $validator->errors());
    }

    public function test_report_labels()
    {
        // Odd, but it is what gets printed today: a draft shows as "Issued".
        $this->assertSame('Issued', StatusLabel::forStatus(0));
        $this->assertSame('Issued', StatusLabel::forStatus(1));
        $this->assertSame('Cancelled', StatusLabel::forStatus(2));
    }
}
