<?php

namespace KhaledHajSalem\ZatcaPhase2\Tests\Feature;

use Illuminate\Foundation\Testing\WithFaker;
use KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException;
use KhaledHajSalem\ZatcaPhase2\Services\CertificateService;
use KhaledHajSalem\ZatcaPhase2\Services\InvoiceService;
use KhaledHajSalem\ZatcaPhase2\Services\ZatcaService;
use KhaledHajSalem\ZatcaPhase2\Tests\Fixtures\InvoiceModel;
use KhaledHajSalem\ZatcaPhase2\Tests\TestCase;
use Mockery;

class ZatcaServiceTest extends TestCase
{
    use WithFaker;

    protected $zatcaService;
    protected $invoiceServiceMock;
    protected $certificateServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->invoiceServiceMock = Mockery::mock(InvoiceService::class);
        $this->certificateServiceMock = Mockery::mock(CertificateService::class);

        // Create the service with mocked dependencies
        $this->zatcaService = new ZatcaService(
            $this->certificateServiceMock,
            $this->invoiceServiceMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }


    /** @test */
    public function it_can_report_an_invoice()
    {
        // Create a mock invoice
        $invoice = Mockery::mock(InvoiceModel::class)
            ->makePartial();
        $invoice->id = 1;
        $invoice->number = 'INV-001';
        $invoice->total = 115;
        $invoice->zatca_invoice_uuid = $this->faker->uuid;

        $this->invoiceServiceMock->shouldReceive('isCreditNote')
            ->with($invoice)
            ->andReturn(false);

        // Setup the mocks
        $invoice->shouldReceive('markAsZatcaReported')
            ->with(['reportingStatus' => 'SUBMITTED', 'requestID' => '123'])
            ->once();

        // Set up mocks for the reporting process
        $this->invoiceServiceMock->shouldReceive('isCreditNote')
            ->with($invoice)
            ->andReturn(false);

        $this->invoiceServiceMock->shouldReceive('generateXml')
            ->with($invoice)
            ->andReturn('<test>xml</test>');

        $this->certificateServiceMock->shouldReceive('signXml')
            ->with('<test>xml</test>')
            ->andReturn('<signed>xml</signed>');

        $this->invoiceServiceMock->shouldReceive('generateUuid')
            ->andReturn($invoice->zatca_invoice_uuid);

        // Mock the protected submitDocument method using reflection
        $zatcaServiceMock = Mockery::mock(ZatcaService::class, [
            $this->certificateServiceMock,
            $this->invoiceServiceMock
        ])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $zatcaServiceMock->shouldReceive('submitDocument')
            ->with($invoice, 'reporting')
            ->andReturn(['reportingStatus' => 'SUBMITTED', 'requestID' => '123']);

        // Ensure the invoice has markAsZatcaReported method
        $invoice->shouldReceive('markAsZatcaReported')
            ->with(['reportingStatus' => 'SUBMITTED', 'requestID' => '123'])
            ->once();

        // Call the method
        $result = $zatcaServiceMock->reportInvoice($invoice);

        // Verify the result
        $this->assertEquals(['reportingStatus' => 'SUBMITTED', 'requestID' => '123'], $result);
    }

    /** @test */
    public function it_can_clear_an_invoice()
    {
        // Create a test invoice
        $invoice = Mockery::mock(InvoiceModel::class)
            ->makePartial();
        $invoice->id = 1;
        $invoice->number = 'INV-001';
        $invoice->total = 115;
        $invoice->zatca_invoice_uuid = $this->faker->uuid;

        // Set up mocks for the clearance process
        $this->invoiceServiceMock->shouldReceive('isCreditNote')
            ->with($invoice)
            ->andReturn(false);

        $this->invoiceServiceMock->shouldReceive('generateXml')
            ->with($invoice)
            ->andReturn('<test>xml</test>');

        $this->certificateServiceMock->shouldReceive('signXml')
            ->with('<test>xml</test>')
            ->andReturn('<signed>xml</signed>');

        $this->invoiceServiceMock->shouldReceive('generateUuid')
            ->andReturn($invoice->zatca_invoice_uuid);

        // Mock the protected submitDocument method using reflection
        $zatcaServiceMock = Mockery::mock(ZatcaService::class, [
            $this->certificateServiceMock,
            $this->invoiceServiceMock
        ])->shouldAllowMockingProtectedMethods()->makePartial();

        $zatcaServiceMock->shouldReceive('submitDocument')
            ->with($invoice, 'clearance')
            ->andReturn(['clearanceStatus' => 'CLEARED', 'requestID' => '123']);

        // Ensure the invoice has markAsZatcaCleared method
        $invoice->shouldReceive('markAsZatcaCleared')
            ->with(['clearanceStatus' => 'CLEARED', 'requestID' => '123'])
            ->once();

        // Call the method
        $result = $zatcaServiceMock->clearInvoice($invoice);

        // Verify the result
        $this->assertEquals(['clearanceStatus' => 'CLEARED', 'requestID' => '123'], $result);
    }

    /** @test */
    public function it_handles_reporting_error()
    {
        // Create a test invoice
        $invoice = Mockery::mock(InvoiceModel::class)
            ->makePartial();
        $invoice->id = 1;
        $invoice->number = 'INV-001';
        $invoice->total = 115;
        $invoice->zatca_invoice_uuid = $this->faker->uuid;

        // Set up mocks to throw an exception
        $this->invoiceServiceMock->shouldReceive('isCreditNote')
            ->with($invoice)
            ->andReturn(false);

        $this->invoiceServiceMock->shouldReceive('generateXml')
            ->with($invoice)
            ->andThrow(new \Exception('XML generation failed'));

        // Ensure the invoice has markAsZatcaFailed method
        $invoice->shouldReceive('markAsZatcaFailed')
            ->withAnyArgs()
            ->once();

        // Expect a ZatcaException to be thrown
        $this->expectException(ZatcaException::class);

        // Call the method
        $this->zatcaService->reportInvoice($invoice);
    }

    /** @test */
    public function it_handles_clearance_error()
    {
        // Create a test invoice
        $invoice = Mockery::mock(InvoiceModel::class)
            ->makePartial();
        $invoice->id = 1;
        $invoice->number = 'INV-001';
        $invoice->total = 115;
        $invoice->zatca_invoice_uuid = $this->faker->uuid;

        // Set up mocks to throw an exception
        $this->invoiceServiceMock->shouldReceive('isCreditNote')
            ->with($invoice)
            ->andReturn(false);

        $this->invoiceServiceMock->shouldReceive('generateXml')
            ->with($invoice)
            ->andThrow(new \Exception('XML generation failed'));

        // Ensure the invoice has markAsZatcaFailed method
        $invoice->shouldReceive('markAsZatcaFailed')
            ->withAnyArgs()
            ->once();

        // Expect a ZatcaException to be thrown
        $this->expectException(ZatcaException::class);

        // Call the method
        $this->zatcaService->clearInvoice($invoice);
    }

    /** @test */
    public function it_can_handle_credit_notes()
    {
        // Create a test credit note
        $creditNote = Mockery::mock(InvoiceModel::class)
            ->makePartial();
        $creditNote->id = 1;
        $creditNote->type = 'CN-001';
        $creditNote->number = 'credit_note';
        $creditNote->total = -115;
        $creditNote->zatca_invoice_uuid = $this->faker->uuid;

        // Set up mocks for the reporting process
        $this->invoiceServiceMock->shouldReceive('isCreditNote')
            ->with($creditNote)
            ->andReturn(true);

        $this->invoiceServiceMock->shouldReceive('generateXml')
            ->with($creditNote)
            ->andReturn('<test>credit-note-xml</test>');

        $this->certificateServiceMock->shouldReceive('signXml')
            ->with('<test>credit-note-xml</test>')
            ->andReturn('<signed>credit-note-xml</signed>');

        $this->invoiceServiceMock->shouldReceive('generateUuid')
            ->andReturn($creditNote->zatca_invoice_uuid);

        // Mock the protected submitDocument method
        $zatcaServiceMock = Mockery::mock(ZatcaService::class, [
            $this->certificateServiceMock,
            $this->invoiceServiceMock
        ])->shouldAllowMockingProtectedMethods()->makePartial();

        $zatcaServiceMock->shouldReceive('submitDocument')
            ->with($creditNote, 'reporting')
            ->andReturn(['reportingStatus' => 'SUBMITTED', 'requestID' => '123']);

        // Ensure the credit note has markAsZatcaReported method
        $creditNote->shouldReceive('markAsZatcaReported')
            ->with(['reportingStatus' => 'SUBMITTED', 'requestID' => '123'])
            ->once();

        // Call the method
        $result = $zatcaServiceMock->reportCreditNote($creditNote);

        // Verify the result
        $this->assertEquals(['reportingStatus' => 'SUBMITTED', 'requestID' => '123'], $result);
    }
}