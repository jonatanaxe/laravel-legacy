<?php

namespace Tests\Unit\Legacy;

use App\Legacy\Support\Text;
use Tests\TestCase;

/**
 * Characterization: freezes what the code DOES today, not what it should do.
 * It extends Laravel's TestCase on purpose: the framework's error handler
 * turns deprecation warnings into exceptions, just like in production.
 */
class TextTest extends TestCase
{
    public function test_initials_of_a_name()
    {
        $this->assertSame('AMS', Text::initials('ana maria souza'));
        $this->assertSame('ZS', Text::initials('  zoë   smith '));
    }

    public function test_slug_transliterates_accents()
    {
        $this->assertSame('cafe-monthly-report', Text::slug('Café Monthly Report'));
    }

    public function test_null_becomes_an_empty_string()
    {
        $this->assertSame('', Text::normalize(null));
        $this->assertSame('abc', Text::normalize('  ABC '));
    }

    public function test_invoice_code_is_zero_padded()
    {
        $this->assertSame('NF-000042', Text::code('NF', 42));
    }

    public function test_sanitize_strips_html()
    {
        $this->assertSame('hi', Text::sanitize('<b>hi</b>'));
    }

    public function test_clean_input_is_a_no_op_without_magic_quotes()
    {
        $this->assertSame("O\\'Neil", Text::cleanInput("O\\'Neil"));
    }

    public function test_date_in_the_city_hall_format()
    {
        $this->assertSame('12/11/2026', Text::date(mktime(12, 0, 0, 11, 12, 2026)));
    }
}
