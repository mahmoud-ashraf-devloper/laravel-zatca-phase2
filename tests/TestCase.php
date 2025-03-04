<?php

namespace KhaledHajSalem\ZatcaPhase2\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use KhaledHajSalem\ZatcaPhase2\ZatcaPhase2ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use WithFaker;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Setup default configuration
        $this->setUpDefaultConfig();
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            ZatcaPhase2ServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup default log channel
        $app['config']->set('logging.channels.zatca', [
            'driver' => 'single',
            'path' => storage_path('logs/zatca-tests.log'),
            'level' => 'debug',
        ]);
    }

    /**
     * Set up default configuration for tests.
     *
     * @return void
     */
    protected function setUpDefaultConfig()
    {
        // Default configuration for tests
        config([
            'zatca.invoice_model' => \KhaledHajSalem\ZatcaPhase2\Tests\Fixtures\InvoiceModel::class,
            'zatca.credit_note_model' => \KhaledHajSalem\ZatcaPhase2\Tests\Fixtures\CreditNoteModel::class,
            'zatca.invoice_table' => 'invoices',
            'zatca.credit_note.identification.method' => 'type_field',
            'zatca.credit_note.identification.type_field' => 'type',
            'zatca.credit_note.identification.type_value' => 'credit_note',
            'zatca.organization.name' => 'Test Company',
            'zatca.organization.tax_number' => '123456789',
            'zatca.log_channel' => 'zatca',
            'zatca.field_mapping' => [
                // Basic default field mappings
                'invoice_number' => 'number',
                'invoice_type' => 'type',
                'issue_date' => 'created_at',
                'issue_time' => 'created_at',
                'invoice_currency_code' => 'currency_code',
                'invoice_counter_value' => 'id',
                'seller_name' => 'seller_name',
                'seller_tax_number' => 'seller_tax_number',
                'buyer_name' => 'buyer_name',
                'buyer_tax_number' => 'buyer_tax_number',
                'line_items' => 'items',
                'item_name' => 'name',
                'item_quantity' => 'quantity',
                'item_unit_code' => 'unit',
                'item_price' => 'unit_price',
                'total_excluding_vat' => 'sub_total',
                'total_including_vat' => 'total',
                'total_vat' => 'vat_amount',
                'total_discount' => 'discount_amount',
                'invoice_note' => 'notes',
                'custom_fields' => 'custom_data',
            ],
        ]);
    }
}