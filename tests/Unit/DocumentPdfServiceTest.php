<?php

namespace KhaledHajSalem\ZatcaPhase2\Tests\Unit;

use KhaledHajSalem\ZatcaPhase2\Services\DocumentPdfService;
use KhaledHajSalem\ZatcaPhase2\Services\InvoiceService;
use KhaledHajSalem\ZatcaPhase2\Support\QrCodeGenerator;
use KhaledHajSalem\ZatcaPhase2\Tests\Fixtures\InvoiceModel;
use KhaledHajSalem\ZatcaPhase2\Tests\TestCase;
use Mockery;

class DocumentPdfServiceTest extends TestCase
{
    protected $pdfService;
    protected $invoiceServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip all tests in this class if Imagick is not available
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick extension is not available.');
        }

        // Mock QrCodeGenerator::generate to avoid actual image generation
        $this->mock(QrCodeGenerator::class, function ($mock) {
            $mock->shouldReceive('generate')
                ->andReturn('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAA');
        });

        // Mock invoice service
        $this->invoiceServiceMock = Mockery::mock(InvoiceService::class);
        $this->app->instance('zatca.invoice', $this->invoiceServiceMock);

        // Create the service
        $this->pdfService = new DocumentPdfService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_generate_pdf_for_invoice()
    {
        // Create a test invoice
        $invoice = new InvoiceModel([
            'id' => 1,
            'number' => 'INV-001',
            'created_at' => now(),
            'seller_name' => 'Test Seller',
            'seller_tax_number' => '123456789',
            'buyer_name' => 'Test Buyer',
            'sub_total' => 100,
            'total' => 115,
            'vat_amount' => 15,
            'zatca_status' => 'submitted',
        ]);

        // Setup mocks
        $this->invoiceServiceMock->shouldReceive('isCreditNote')
            ->with($invoice)
            ->andReturn(false);

        $this->invoiceServiceMock->shouldReceive('getFieldValue')
            ->andReturnUsing(function ($obj, $field, $default = null) {
                return $obj->{$field} ?? $default;
            });

        // Generate PDF
        $pdf = $this->pdfService->generatePdf($invoice);

        // Assert type and content
        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-1.', $pdf);
    }

    /** @test */
    public function it_can_generate_pdf_for_credit_note()
    {
        // Create a test credit note
        $creditNote = new InvoiceModel([
            'id' => 2,
            'number' => 'CN-001',
            'type' => 'credit_note',
            'created_at' => now(),
            'seller_name' => 'Test Seller',
            'seller_tax_number' => '123456789',
            'buyer_name' => 'Test Buyer',
            'sub_total' => -100,
            'total' => -115,
            'vat_amount' => -15,
            'zatca_status' => 'submitted',
            'original_invoice_number' => 'INV-001',
        ]);

        // Setup mocks
        $this->invoiceServiceMock->shouldReceive('isCreditNote')
            ->with($creditNote)
            ->andReturn(true);

        $this->invoiceServiceMock->shouldReceive('getFieldValue')
            ->andReturnUsing(function ($obj, $field, $default = null) {
                return $obj->{$field} ?? $default;
            });

        // Generate PDF
        $pdf = $this->pdfService->generatePdf($creditNote);

        // Assert type and content
        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-1.', $pdf);
    }

    /** @test */
    public function it_can_generate_pdf_with_custom_options()
    {
        // Create a test invoice
        $invoice = new InvoiceModel([
            'id' => 1,
            'number' => 'INV-001',
            'created_at' => now(),
            'seller_name' => 'Test Seller',
            'seller_tax_number' => '123456789',
            'buyer_name' => 'Test Buyer',
            'sub_total' => 100,
            'total' => 115,
            'vat_amount' => 15,
        ]);

        // Setup mocks
        $this->invoiceServiceMock->shouldReceive('isCreditNote')
            ->with($invoice)
            ->andReturn(false);

        $this->invoiceServiceMock->shouldReceive('getFieldValue')
            ->andReturnUsing(function ($obj, $field, $default = null) {
                return $obj->{$field} ?? $default;
            });

        // Generate PDF with custom options
        $pdf = $this->pdfService->generatePdf($invoice, [
            'paper' => 'a4',
            'orientation' => 'landscape',
            'custom_css' => 'body { font-size: 14px; }',
        ]);

        // Assert type and content
        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-1.', $pdf);
    }

    /** @test */
    public function it_handles_errors_during_pdf_generation()
    {
        // Create an invalid invoice (missing required fields)
        $invalidInvoice = new InvoiceModel();

        // Setup mocks to throw an exception
        $this->invoiceServiceMock->shouldReceive('isCreditNote')
            ->with($invalidInvoice)
            ->andThrow(new \Exception('Failed to determine document type'));

        // Expect exception
        $this->expectException(\KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException::class);

        // Try to generate PDF
        $this->pdfService->generatePdf($invalidInvoice);
    }
}