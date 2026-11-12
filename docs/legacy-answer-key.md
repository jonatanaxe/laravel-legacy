# Legacy module answer key (`app/Legacy`)

The module simulates the **SIG**, a 2009-2016 billing system ported to Laravel
in 2017 "as it was". Everything runs clean on PHP 7.2 / Laravel 5.8, and the
29 tests in `tests/Unit/Legacy` pass. Each item below only breaks on a future
version.

**How to use it:** don't read this table before studying the tools. Run
Rector, PHPCompatibility and PHPStan first, write down what each one found,
then compare here. The "Found by" column is for you to fill in.

Kinds of breakage:

- **fatal**: the class doesn't even load, or the function no longer exists;
- **error**: throws an exception at run time;
- **silent**: nothing errors, but the result changes (only a test catches it);
- **deprecated**: becomes a warning. Laravel 5.8 turns every warning into an
  exception (`HandleExceptions`, `error_reporting(-1)`), so it breaks the test
  here too.

## PHP

| # | Where | Problem | Breaks on | Kind | Test that catches it | Found by |
|---|---|---|---|---|---|---|
| 1 | `Support/Text.php:24` | `$part{0}` (curly brace offset) | 7.4 deprecated, 8.0 removed | fatal | `TextTest::test_initials_of_a_name` | |
| 2 | `Support/Text.php:66` | `get_magic_quotes_gpc()` | 7.4 deprecated, 8.0 removed | fatal | `TextTest::test_clean_input_...` | |
| 3 | `Integration/PartnerExport.php:30` | `implode($array, $glue)` (reversed order) | 7.4 deprecated, 8.0 removed | error | `IntegrationTest::test_partner_csv_line` | |
| 4 | `Integration/PartnerExport.php:15` | WDDX extension | 7.4 removed from core | fatal | `IntegrationTest::test_wddx_packet` | |
| 5 | `Billing/StatusLabel.php:12` | nested ternary without parentheses | 7.4 deprecated, 8.0 syntax error | fatal | `test_report_labels` + the whole report | |
| 6 | `Billing/InvoiceValidator.php:24` | `array_key_exists()` on an object | 7.4 deprecated, 8.0 error | error | `test_number_is_required` | |
| 7 | `Billing/InvoiceValidator.php:17` | `'sent' == 0` | 8.0 changes the result | silent | `test_text_status_is_treated_as_draft` | |
| 8 | `Billing/InvoiceValidator.php:31` | `in_array(0, [..., 'EXEMPT'])` without strict | 8.0 changes the result | silent | `test_blank_code_passes` | |
| 9 | `Billing/InvoiceValidator.php:36` | `is_numeric('2 ')` | 8.0 changes (accepts trailing space) | silent | `test_quantity_with_trailing_csv_space_is_invalid` | |
| 10 | `Billing/Customer.php:28` | `substr()` out of range | 8.0 returns `''` instead of `false` | silent | `test_short_document_falls_back_to_head_office` | |
| 11 | `Integration/GatewayClient.php:18` | `is_resource($curl)` | 8.0: cURL becomes a `CurlHandle` object | error | `test_gateway_opens_builds_url_and_closes` | |
| 12 | `Billing/Invoice.php:32` | required parameter after an optional one | 8.0 deprecated | deprecated | every test using `addItem` | |
| 13 | `Support/Text.php:42` | `trim(null)` | 8.1 deprecated | deprecated | `test_null_becomes_an_empty_string` | |
| 14 | `Billing/InvoiceRecord.php:59` | `json_decode(null)` | 8.1 deprecated | deprecated | `test_record_without_items_becomes_an_empty_invoice` | |
| 15 | `Billing/ItemCollection.php:9` | `ArrayAccess`/`Countable`/`IteratorAggregate` without return types | 8.1 deprecated | deprecated | every test using items | |
| 16 | `Billing/ItemCollection.php:9` | `Serializable` without `__serialize()` | 8.1 deprecated | deprecated | `test_items_count_and_survive_serialization` | |
| 17 | `Support/Text.php:58` | `FILTER_SANITIZE_STRING` | 8.1 deprecated | deprecated | `test_sanitize_strips_html` | |
| 18 | `Support/Text.php:78` | `strftime()` | 8.1 deprecated | deprecated | `test_date_in_the_city_hall_format` | |
| 19 | `Billing/Invoice.php:28` | dynamic property (`notes`, `legacy_id`) | 8.2 deprecated | deprecated | `test_extra_column_becomes_an_attribute` | |
| 20 | `Support/Text.php:50` | `"${prefix}"` interpolation | 8.2 deprecated | deprecated | `test_invoice_code_is_zero_padded` | |
| 21 | `Integration/PartnerExport.php:35` | `utf8_decode()` | 8.2 deprecated | deprecated | `test_name_goes_out_in_latin1` | |
| 22 | `Billing/Invoice.php:21` | implicit nullable `Customer $customer = null` | 8.4 deprecated | deprecated | every test creating an invoice | |

## Laravel

| # | Where | Problem | Breaks on | Test that catches it | Found by |
|---|---|---|---|---|---|
| 23 | `Support/Text.php:33` | `str_slug()` helper | Laravel 6 (removed) | `test_slug_transliterates_accents` | |
| 24 | `Billing/InvoiceValidator.php:29`, `Reports/BillingReport.php:27-29` | `array_get()` helper | Laravel 6 (removed) | validator and report | |
| 25 | `Billing/InvoiceRecord.php:16` | `$dates` property | Laravel 10 (removed; use `$casts`) | `test_dates_become_carbon` | |
| 26 | `Reports/BillingReport.php:18` | `orderByRaw(DB::raw(...))`, an `Expression` used as a string | Laravel 10 | `test_summary_by_status_sorted_by_amount` | |

## Debt that doesn't break on upgrade (but a reviewer points it out)

- `InvoiceRecord::$guarded = []`: open mass assignment.
- `StatusLabel` prints "Issued" for a draft (a consequence of item 5). The
  test freezes the bug on purpose: fixing it changes behavior and needs a
  heads-up to whoever reads the report. The Billing screen shows it live.
- `status` mixes `0/1/2` and `draft/sent` in the same column.

## Questions to practice

1. Which items does **no tool** find, so only the test catches them? (hint:
   the "silent" ones)
2. Why does item 5 break tests that don't even call `StatusLabel` directly?
3. Item 7 is a bug that PHP 8 "fixes". Why is it still a breaking change?
4. What is the right fix order: the 8.1 deprecations before or after moving
   to 8.0?
