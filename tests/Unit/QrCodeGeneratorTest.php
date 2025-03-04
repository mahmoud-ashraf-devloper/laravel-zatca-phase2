<?php

namespace KhaledHajSalem\ZatcaPhase2\Tests\Unit;

use Carbon\Carbon;
use KhaledHajSalem\ZatcaPhase2\Support\QrCodeGenerator;
use KhaledHajSalem\ZatcaPhase2\Tests\Fixtures\InvoiceModel;
use KhaledHajSalem\ZatcaPhase2\Tests\TestCase;

class QrCodeGeneratorTest extends TestCase
{
    /** @test */
    public function it_can_generate_qr_code_for_invoice()
    {
        // Skip test if Imagick is not available
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick extension is not available.');
        }

        $issueDate = Carbon::parse('2023-01-15 10:30:00');

        $invoice = new InvoiceModel([
            'id' => 1,
            'number' => 'INV-001',
            'type' => 'invoice',
            'created_at' => $issueDate,
            'seller_name' => 'Test Seller',
            'seller_tax_number' => '123456789',
            'total' => 115,
            'vat_amount' => 15,
        ]);

        // Generate QR code
        $qrCode = QrCodeGenerator::generate($invoice);

        // Assertions
        $this->assertIsString($qrCode);
        $this->assertStringStartsWith('data:image/png;base64,', $qrCode);

        // Extract the base64 data
        $base64Data = str_replace('data:image/png;base64,', '', $qrCode);

        // Verify it's valid base64
        $this->assertTrue(base64_decode($base64Data, true) !== false);
    }

    /** @test */
    public function it_can_generate_qr_code_for_credit_note()
    {
        // Skip test if Imagick is not available
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick extension is not available.');
        }

        $issueDate = Carbon::parse('2023-01-15 10:30:00');

        $creditNote = new InvoiceModel([
            'id' => 2,
            'number' => 'CN-001',
            'type' => 'credit_note',
            'created_at' => $issueDate,
            'seller_name' => 'Test Seller',
            'seller_tax_number' => '123456789',
            'total' => -115,
            'vat_amount' => -15,
            'original_invoice_id' => 1,
            'originalInvoice' => [
                'number' => 'INV-001',
                'zatca_invoice_uuid' => '123e4567-e89b-12d3-a456-426614174000',
            ],
        ]);

        // Configure test
        config([
            'zatca.credit_note.identification.method' => 'type_field',
            'zatca.credit_note.invoice_reference' => [
                'field' => 'original_invoice_id',
                'number_reference' => 'originalInvoice.number',
                'uuid_reference' => 'originalInvoice.zatca_invoice_uuid',
            ],
        ]);

        // Generate QR code
        $qrCode = QrCodeGenerator::generate($creditNote);

        // Assertions
        $this->assertIsString($qrCode);
        $this->assertStringStartsWith('data:image/png;base64,', $qrCode);

        // Extract the base64 data
        $base64Data = str_replace('data:image/png;base64,', '', $qrCode);

        // Verify it's valid base64
        $this->assertTrue(base64_decode($base64Data, true) !== false);
    }

    /** @test */
    public function it_handles_error_during_qr_code_generation()
    {
        // Create an invalid invoice (missing required fields)
        $invalidInvoice = new InvoiceModel();

        // Mock the app container to return a null invoice service
        $this->app->instance('zatca.invoice', null);

        // Expect exception
        $this->expectException(\KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException::class);

        // Try to generate QR code
        QrCodeGenerator::generate($invalidInvoice);
    }
}