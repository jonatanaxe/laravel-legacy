<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices imported from the SIG.
 *
 * status is a string because it holds both worlds: '0'/'1'/'2' (SIG) and
 * 'draft'/'sent' (newer screens). payload is the items' JSON.
 */
class CreateLegacyInvoicesTable extends Migration
{
    public function up()
    {
        Schema::create('legacy_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('number');
            $table->string('customer_name', 200);
            $table->string('customer_document', 20);
            $table->string('status', 16)->default('0');
            $table->decimal('total', 12, 2)->default(0);
            $table->text('payload')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'issued_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('legacy_invoices');
    }
}
