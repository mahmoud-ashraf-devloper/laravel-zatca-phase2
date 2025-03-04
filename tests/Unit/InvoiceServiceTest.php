<?php

namespace KhaledHajSalem\ZatcaPhase2\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use KhaledHajSalem\ZatcaPhase2\Services\InvoiceService;
use KhaledHajSalem\ZatcaPhase2\Tests\TestCase;
use KhaledHajSalem\ZatcaPhase2\Tests\Fixtures\InvoiceModel;
use KhaledHajSalem\ZatcaPhase2\Tests\Fixtures\CreditNoteModel;

class InvoiceServiceTest extends TestCase
{
    protected $invoiceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->invoiceService = new InvoiceService();
    }

    /** @test */
    public function it_can_generate_uuid()
    {
        $uuid = $this->invoiceService->generateUuid();
        $this->assertIsString($uuid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid);
    }

    /** @test */
    public function it_can_generate_hash_for_invoice()
    {
        $invoice = new InvoiceModel([
            'id' => 1,
            'number' => 'INV-001',
            'total' => 100,
        ]);

        $hash = $this->invoiceService->generateHash($invoice);
        $this->assertIsString($hash);
        $this->assertEquals(64, strlen($hash)); // SHA-256 hash is 64 characters long
    }

    /** @test */
    public function it_can_identify_invoice_versus_credit_note_using_type_field()
    {
        // Set up config for type field identification
        config([
            'zatca.credit_note.identification.method' => 'type_field',
            'zatca.credit_note.identification.type_field' => 'type',
            'zatca.credit_note.identification.type_value' => 'credit_note',
        ]);

        $invoice = new InvoiceModel(['type' => 'invoice']);
        $creditNote = new InvoiceModel(['type' => 'credit_note']);

        $this->assertFalse($this->invoiceService->isCreditNote($invoice));
        $this->assertTrue($this->invoiceService->isCreditNote($creditNote));
    }

    /** @test */
    public function it_can_identify_invoice_versus_credit_note_using_model_type()
    {
        // Set up config for model identification
        config([
            'zatca.credit_note.identification.method' => 'model',
            'zatca.credit_note_model' => CreditNoteModel::class,
            'zatca.invoice_model' => InvoiceModel::class,
        ]);

        $invoice = new InvoiceModel();
        $creditNote = new CreditNoteModel();

        $this->assertFalse($this->invoiceService->isCreditNote($invoice));
        $this->assertTrue($this->invoiceService->isCreditNote($creditNote));
    }

    /** @test */
    public function it_can_get_field_value_from_object()
    {
        $invoice = new InvoiceModel([
            'number' => 'INV-001',
            'customer' => [
                'name' => 'Test Customer',
                'tax_number' => '123456789',
            ],
        ]);

        // Direct field
        $this->assertEquals('INV-001', $this->invoiceService->getFieldValue($invoice, 'number'));

        // Nested field with dot notation
        $this->assertEquals('Test Customer', $this->invoiceService->getFieldValue($invoice, 'customer.name'));

        // Non-existent field with default
        $this->assertEquals('default', $this->invoiceService->getFieldValue($invoice, 'non_existent', 'default'));

        // Empty path with default
        $this->assertEquals('default', $this->invoiceService->getFieldValue($invoice, '', 'default'));
    }

    /** @test */
    public function it_maps_document_data_for_invoice()
    {
        // Set up mock field mappings
        config([
            'zatca.field_mapping' => [
                'invoice_number' => 'number',
                'invoice_type' => 'type',
                'issue_date' => 'created_at',
                'issue_time' => 'created_at',
                'seller_name' => 'company.name',
                'seller_tax_number' => null,
                'buyer_name' => 'customer.name',
                'invoice_currency_code' => 'currency_code',
                'invoice_counter_value' => 'id',
                'total_excluding_vat' => 'sub_total',
                'total_including_vat' => 'total',
                'total_vat' => 'vat_amount',
                'line_items' => 'items',
                'item_name' => 'name',
                'item_quantity' => 'quantity',
                'item_price' => 'unit_price',
                'seller_address' => 'seller_address',
                'seller_street' => 'seller_street',
                'seller_building_number' => 'seller_building_number',
                'seller_postal_code' => 'seller_postal_code',
                'seller_city' => 'seller_city',
                'seller_district' => 'seller_district',
                'seller_additional_number' => 'seller_additional_number',
                'seller_country_code' => 'seller_country_code',
                'buyer_tax_number' => 'customer.tax_number',
                'buyer_address' => 'customer.address',
                'buyer_street' => 'customer.street',
                'buyer_building_number' => 'customer.building_number',
                'buyer_postal_code' => 'customer.postal_code',
                'buyer_city' => 'customer.city',
                'buyer_district' => 'customer.district',
                'buyer_additional_number' => 'customer.additional_number',
                'buyer_country_code' => 'customer.country_code',
                'item_unit_code' => 'unit',
                'item_price_inclusive' => 'price_inclusive_vat',
                'item_discount' => 'discount_amount',
                'item_discount_reason' => 'discount_reason',
                'item_tax_category' => 'vat_category',
                'item_tax_rate' => 'vat_rate',
                'item_tax_amount' => 'vat_amount',
                'total_discount' => 'discount_amount',
                'supply_date' => null,
                'supply_end_date' => null,
                'special_tax_treatment' => null,
                'invoice_note' => 'notes',
                'custom_fields' => 'custom_data',
            ],
            'zatca.organization.name' => 'Test Company',
            'zatca.organization.tax_number' => '123456789',
        ]);

        $invoice = new InvoiceModel([
            'number' => 'INV-001',
            'type' => 'invoice',
            'created_at' => now(),
            'sub_total' => 100,
            'total' => 115,
            'vat_amount' => 15,
            'currency_code' => 'SAR',
            'company' => [
                'name' => 'Seller Company',
            ],
            'customer' => [
                'name' => 'Test Customer',
            ],
            'items' => [
                [
                    'name' => 'Product 1',
                    'quantity' => 2,
                    'unit_price' => 50,
                ],
            ],
        ]);

        // Use reflection to access protected method
        $reflector = new \ReflectionClass($this->invoiceService);
        $method = $reflector->getMethod('mapDocumentData');
        $method->setAccessible(true);

        $mappedData = $method->invokeArgs($this->invoiceService, [$invoice, false]);

        // Verify mapped data
        $this->assertEquals('INV-001', $mappedData['invoice_number']);
        $this->assertEquals('388', $mappedData['invoice_type']); // Default for invoice
        $this->assertEquals('Seller Company', $mappedData['seller_name']);
        $this->assertEquals('Test Customer', $mappedData['buyer_name']);
        $this->assertEquals(100, $mappedData['total_excluding_vat']);
        $this->assertEquals(115, $mappedData['total_including_vat']);
        $this->assertEquals(15, $mappedData['total_vat']);

        // Verify line items
        $this->assertCount(1, $mappedData['line_items']);
        $this->assertEquals('Product 1', $mappedData['line_items'][0]['name']);
        $this->assertEquals(2, $mappedData['line_items'][0]['quantity']);
        $this->assertEquals(50, $mappedData['line_items'][0]['price']);
    }

    /** @test */
    public function it_maps_document_data_for_credit_note()
    {
        // Set up mock field mappings
        config([
            'zatca.field_mapping' => [
                'invoice_number' => 'number',
                'invoice_type' => 'type',
                'issue_date' => 'created_at',
                'issue_time' => 'created_at',
                'seller_name' => 'company.name',
                'seller_tax_number' => null,
                'buyer_name' => 'customer.name',
                'invoice_currency_code' => 'currency_code',
                'invoice_counter_value' => 'id',
                'total_excluding_vat' => 'sub_total',
                'total_including_vat' => 'total',
                'total_vat' => 'vat_amount',
                'line_items' => 'items',
                'item_name' => 'name',
                'item_quantity' => 'quantity',
                'item_price' => 'unit_price',
                'seller_address' => 'seller_address',
                'seller_street' => 'seller_street',
                'seller_building_number' => 'seller_building_number',
                'seller_postal_code' => 'seller_postal_code',
                'seller_city' => 'seller_city',
                'seller_district' => 'seller_district',
                'seller_additional_number' => 'seller_additional_number',
                'seller_country_code' => 'seller_country_code',
                'buyer_tax_number' => 'customer.tax_number',
                'buyer_address' => 'customer.address',
                'buyer_street' => 'customer.street',
                'buyer_building_number' => 'customer.building_number',
                'buyer_postal_code' => 'customer.postal_code',
                'buyer_city' => 'customer.city',
                'buyer_district' => 'customer.district',
                'buyer_additional_number' => 'customer.additional_number',
                'buyer_country_code' => 'customer.country_code',
                'item_unit_code' => 'unit',
                'item_price_inclusive' => 'price_inclusive_vat',
                'item_discount' => 'discount_amount',
                'item_discount_reason' => 'discount_reason',
                'item_tax_category' => 'vat_category',
                'item_tax_rate' => 'vat_rate',
                'item_tax_amount' => 'vat_amount',
                'total_discount' => 'discount_amount',
                'supply_date' => null,
                'supply_end_date' => null,
                'special_tax_treatment' => null,
                'invoice_note' => 'notes',
                'custom_fields' => 'custom_data',
            ],
            'zatca.credit_note.invoice_reference' => [
                'field' => 'original_invoice_id',
                'number_reference' => 'originalInvoice.number',
                'uuid_reference' => 'originalInvoice.zatca_invoice_uuid',
            ],
        ]);

        $creditNote = new InvoiceModel([
            'number' => 'CN-001',
            'type' => 'credit_note',
            'created_at' => now(),
            'sub_total' => -100,
            'total' => -115,
            'vat_amount' => -15,
            'original_invoice_id' => 1,
            'currency_code' => 'SAR',
            'originalInvoice' => [
                'number' => 'INV-001',
                'zatca_invoice_uuid' => '123e4567-e89b-12d3-a456-426614174000',
            ],
        ]);

        // Use reflection to access protected method
        $reflector = new \ReflectionClass($this->invoiceService);
        $method = $reflector->getMethod('mapDocumentData');
        $method->setAccessible(true);

        $mappedData = $method->invokeArgs($this->invoiceService, [$creditNote, true]);

        // Verify mapped data
        $this->assertEquals('CN-001', $mappedData['invoice_number']);
        $this->assertEquals('381', $mappedData['invoice_type']); // Code for credit note
        $this->assertEquals(-100, $mappedData['total_excluding_vat']);
        $this->assertEquals(-115, $mappedData['total_including_vat']);
        $this->assertEquals(-15, $mappedData['total_vat']);

        // Verify credit note specific fields
        $this->assertEquals('INV-001', $mappedData['billing_reference_id']);
        $this->assertEquals('123e4567-e89b-12d3-a456-426614174000', $mappedData['billing_reference_uuid']);
    }
}