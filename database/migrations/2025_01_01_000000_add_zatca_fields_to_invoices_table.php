<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class AddZatcaFieldsToInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $invoiceTable = Config::get('zatca.invoice_table', 'invoices');

        Schema::table($invoiceTable, function (Blueprint $table) use($invoiceTable) {
            // Check if the columns already exist before adding them
            if (!Schema::hasColumn($invoiceTable, 'zatca_status')) {
                $table->string('zatca_status')->nullable()->comment('ZATCA compliance status');
            }

            if (!Schema::hasColumn($invoiceTable, 'zatca_response')) {
                $table->json('zatca_response')->nullable()->comment('ZATCA API response data');
            }

            if (!Schema::hasColumn($invoiceTable, 'zatca_cleared_at')) {
                $table->timestamp('zatca_cleared_at')->nullable()->comment('When the invoice was cleared by ZATCA');
            }

            if (!Schema::hasColumn($invoiceTable, 'zatca_reported_at')) {
                $table->timestamp('zatca_reported_at')->nullable()->comment('When the invoice was reported to ZATCA');
            }

            if (!Schema::hasColumn($invoiceTable, 'zatca_compliance_invoice_id')) {
                $table->string('zatca_compliance_invoice_id')->nullable()->comment('ZATCA compliance invoice identifier');
            }

            if (!Schema::hasColumn($invoiceTable, 'zatca_invoice_hash')) {
                $table->string('zatca_invoice_hash')->nullable()->comment('ZATCA invoice hash');
            }

            if (!Schema::hasColumn($invoiceTable, 'zatca_invoice_uuid')) {
                $table->uuid('zatca_invoice_uuid')->nullable()->comment('ZATCA invoice UUID');
            }

            if (!Schema::hasColumn($invoiceTable, 'zatca_qr_code')) {
                $table->text('zatca_qr_code')->nullable()->comment('Base64 encoded QR code');
            }

            if (!Schema::hasColumn($invoiceTable, 'zatca_xml')) {
                $table->longText('zatca_xml')->nullable()->comment('Generated XML for ZATCA');
            }

            if (!Schema::hasColumn($invoiceTable, 'zatca_errors')) {
                $table->json('zatca_errors')->nullable()->comment('Errors encountered during ZATCA processing');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $invoiceTable = Config::get('zatca.invoice_table', 'invoices');

        Schema::table($invoiceTable, function (Blueprint $table) {
            // Drop columns if they exist
            $columns = [
                'zatca_status',
                'zatca_response',
                'zatca_cleared_at',
                'zatca_reported_at',
                'zatca_compliance_invoice_id',
                'zatca_invoice_hash',
                'zatca_invoice_uuid',
                'zatca_qr_code',
                'zatca_xml',
                'zatca_errors'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}