<?php

namespace Tests\Unit\Legacy;

use App\Legacy\Billing\Customer;
use App\Legacy\Billing\Invoice;
use App\Legacy\Integration\GatewayClient;
use App\Legacy\Integration\PartnerExport;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    private function invoice()
    {
        return (new Invoice(array('number' => 42), new Customer('Zoë', '12.345.678/0001-95')))
            ->addItem('Technical hour', 2, 150)
            ->addItem('Travel', 1, 80);
    }

    public function test_gateway_opens_builds_url_and_closes()
    {
        $client = new GatewayClient('https://gw.example/');

        $this->assertTrue($client->isOpen());
        $this->assertSame('https://gw.example/charges?id=7', $client->url('/charges', array('id' => 7)));

        $client->close();

        $this->assertFalse($client->isOpen());
    }

    public function test_partner_csv_line()
    {
        $this->assertSame('NF-000042;12345678000195;380,00', (new PartnerExport())->csvLine($this->invoice()));
    }

    public function test_wddx_packet()
    {
        $packet = (new PartnerExport())->toWddx($this->invoice());

        $this->assertContains('<wddxPacket', $packet);
        $this->assertContains('NF-000042', $packet);
    }

    public function test_name_goes_out_in_latin1()
    {
        // "Zoë" is 4 bytes in UTF-8 and 3 in ISO-8859-1.
        $this->assertSame(3, strlen((new PartnerExport())->latin1('Zoë')));
    }
}
