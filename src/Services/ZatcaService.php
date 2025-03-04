<?php

namespace KhaledHajSalem\ZatcaPhase2\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException;
use KhaledHajSalem\ZatcaPhase2\Support\QrCodeGenerator;

class ZatcaService
{
    /**
     * Certificate service instance.
     *
     * @var \KhaledHajSalem\ZatcaPhase2\Services\CertificateService
     */
    protected $certificateService;

    /**
     * Invoice service instance.
     *
     * @var \KhaledHajSalem\ZatcaPhase2\Services\InvoiceService
     */
    protected $invoiceService;

    /**
     * HTTP client instance.
     *
     * @var \GuzzleHttp\Client
     */
    public $client;

    /**
     * ZATCA base API URL.
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * ZATCA compliance URL.
     *
     * @var string
     */
    protected $complianceUrl;

    /**
     * ZATCA reporting URL.
     *
     * @var string
     */
    protected $reportingUrl;

    /**
     * ZATCA clearance URL.
     *
     * @var string
     */
    protected $clearanceUrl;

    /**
     * ZATCA status URL.
     *
     * @var string
     */
    public $statusUrl;

    /**
     * Create a new ZATCA service instance.
     *
     * @param  \KhaledHajSalem\ZatcaPhase2\Services\CertificateService  $certificateService
     * @param  \KhaledHajSalem\ZatcaPhase2\Services\InvoiceService  $invoiceService
     * @return void
     */
    public function __construct(CertificateService $certificateService, InvoiceService $invoiceService)
    {
        $this->certificateService = $certificateService;
        $this->invoiceService = $invoiceService;

        // Get current environment
        $environment = config('zatca.environment', 'sandbox');
        $environmentConfig = config("zatca.environments.{$environment}", []);

        // Set up API URLs
        $this->baseUrl = $environmentConfig['base_url'] ?? config('zatca.api.base_url');
        $this->complianceUrl = $environmentConfig['compliance_url'] ?? config('zatca.api.compliance_url');
        $this->reportingUrl = $environmentConfig['reporting_url'] ?? config('zatca.api.reporting_url');
        $this->clearanceUrl = $environmentConfig['clearance_url'] ?? config('zatca.api.clearance_url');
        $this->statusUrl = $environmentConfig['status_url'] ?? config('zatca.api.status_url');

        // Initialize HTTP client
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'verify' => true, // Set to false for testing if needed
        ]);

        Log::channel(config('zatca.log_channel', 'zatca'))->info('ZATCA Service initialized', [
            'environment' => $environment,
            'base_url' => $this->baseUrl
        ]);
    }

    /**
     * Check if sandbox environment is active.
     *
     * @return bool
     */
    public function isSandboxMode()
    {
        return config('zatca.environment', 'sandbox') === 'sandbox';
    }

    /**
     * Check if production environment is active.
     *
     * @return bool
     */
    public function isProductionMode()
    {
        return config('zatca.environment', 'sandbox') === 'production';
    }

    /**
     * Report a document (invoice or credit note) to ZATCA.
     *
     * @param  mixed  $document  Invoice or credit note
     * @return array
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public function reportDocument($document)
    {
        try {
            // Determine if document is a credit note
            $isCreditNote = $this->invoiceService->isCreditNote($document);
            $documentType = $isCreditNote ? 'credit note' : 'invoice';

            // Generate document XML
            $documentXml = $this->invoiceService->generateXml($document);

            // Sign XML with certificate
            $signedDocumentXml = $this->certificateService->signXml($documentXml);

            // Generate QR code
            $qrCode = QrCodeGenerator::generate($document);

            // Store XML and QR code on document
            $document->zatca_xml = $signedDocumentXml;
            $document->zatca_qr_code = $qrCode;
            $document->zatca_invoice_uuid = $this->invoiceService->generateUuid();
            $document->save();

            // Send to ZATCA
            $response = $this->submitDocument($document, 'reporting');

            // Process response
            if (isset($response['reportingStatus']) && $response['reportingStatus'] === 'SUBMITTED') {
                $document->markAsZatcaReported($response);
            } else {
                $document->markAsZatcaFailed($response);
                throw new ZatcaException($documentType . ' reporting failed: ' . json_encode($response));
            }

            return $response;
        } catch (\Exception $e) {
            $this->logError('Error reporting ' . $documentType, $e);
            $document->markAsZatcaFailed(['error' => $e->getMessage()]);
            throw new ZatcaException('Failed to report ' . $documentType . ': ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Request clearance for a document (invoice or credit note) from ZATCA.
     *
     * @param  mixed  $document  Invoice or credit note
     * @return array
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public function clearDocument($document)
    {
        try {
            // Determine if document is a credit note
            $isCreditNote = $this->invoiceService->isCreditNote($document);
            $documentType = $isCreditNote ? 'credit note' : 'invoice';

            // Generate document XML
            $documentXml = $this->invoiceService->generateXml($document);

            // Sign XML with certificate
            $signedDocumentXml = $this->certificateService->signXml($documentXml);

            // Generate QR code
            $qrCode = QrCodeGenerator::generate($document);

            // Store XML and QR code on document
            $document->zatca_xml = $signedDocumentXml;
            $document->zatca_qr_code = $qrCode;
            $document->zatca_invoice_uuid = $this->invoiceService->generateUuid();
            $document->save();

            // Send to ZATCA
            $response = $this->submitDocument($document, 'clearance');

            // Process response
            if (isset($response['clearanceStatus']) && $response['clearanceStatus'] === 'CLEARED') {
                $document->markAsZatcaCleared($response);
            } else {
                $document->markAsZatcaFailed($response);
                throw new ZatcaException($documentType . ' clearance failed: ' . json_encode($response));
            }

            return $response;
        } catch (\Exception $e) {
            $this->logError('Error clearing ' . $documentType, $e);
            $document->markAsZatcaFailed(['error' => $e->getMessage()]);
            throw new ZatcaException('Failed to clear ' . $documentType . ': ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Report an invoice to ZATCA.
     *
     * @param  mixed  $invoice
     * @return array
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public function reportInvoice($invoice)
    {
        return $this->reportDocument($invoice);
    }

    /**
     * Request clearance for an invoice from ZATCA.
     *
     * @param  mixed  $invoice
     * @return array
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public function clearInvoice($invoice)
    {
        return $this->clearDocument($invoice);
    }

    /**
     * Report a credit note to ZATCA.
     *
     * @param  mixed  $creditNote
     * @return array
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public function reportCreditNote($creditNote)
    {
        return $this->reportDocument($creditNote);
    }

    /**
     * Request clearance for a credit note from ZATCA.
     *
     * @param  mixed  $creditNote
     * @return array
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public function clearCreditNote($creditNote)
    {
        return $this->clearDocument($creditNote);
    }

    /**
     * Check the status of an invoice in ZATCA.
     *
     * @param  mixed  $invoice
     * @return array
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public function checkInvoiceStatus($invoice)
    {
        try {
            $url = $this->statusUrl;

            if (!$invoice->zatca_invoice_uuid) {
                throw new ZatcaException('Invoice does not have a ZATCA UUID');
            }

            $headers = $this->getAuthHeaders();

            $response = $this->client->request('GET', $url, [
                'headers' => $headers,
                'query' => [
                    'uuid' => $invoice->zatca_invoice_uuid,
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            return $result;
        } catch (GuzzleException $e) {
            $this->logError('Error checking invoice status', $e);
            throw new ZatcaException('Failed to check invoice status: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Submit an invoice to ZATCA for reporting or clearance.
     *
     * @param  mixed  $document  Invoice or credit note
     * @param  string  $type  Either 'reporting' or 'clearance'
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function submitDocument($document, $type)
    {
        $endpoint = $type === 'reporting'
            ? $this->reportingUrl
            : $this->clearanceUrl;

        $headers = $this->getAuthHeaders();

        $payload = [
            'invoiceHash' => $document->zatca_invoice_hash ?? $this->invoiceService->generateHash($document),
            'uuid' => $document->zatca_invoice_uuid,
            'invoice' => base64_encode($document->zatca_xml),
        ];

        // Check if this is a credit note and adjust payload if necessary
        if ($this->invoiceService->isCreditNote($document)) {
            // Some ZATCA implementations might require additional flags for credit notes
            $payload['documentType'] = 'CreditNote';
        }

        // Log the request in development
        if (app()->environment('local', 'development')) {
            Log::channel(config('zatca.log_channel', 'zatca'))->debug('ZATCA API Request', [
                'endpoint' => $endpoint,
                'environment' => $this->isSandboxMode() ? 'sandbox' : 'production',
                'uuid' => $document->zatca_invoice_uuid,
                'document_type' => $this->invoiceService->isCreditNote($document) ? 'credit_note' : 'invoice',
            ]);
        }

        $response = $this->client->request('POST', $endpoint, [
            'headers' => $headers,
            'json' => $payload,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get ZATCA API authentication headers.
     *
     * @return array
     */
    protected function getAuthHeaders()
    {
        if ($this->isSandboxMode()) {
            // Use sandbox credentials
            $certificateId = config('zatca.sandbox.certificate_id');
            $pih = config('zatca.sandbox.pih');
        } else {
            // Use production credentials
            $certificateData = $this->certificateService->getCertificateData();
            $certificateId = $certificateData['certificate_id'];
            $pih = config('zatca.pih');
        }

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($certificateId . ':' . $pih),
        ];
    }

    /**
     * Log an error.
     *
     * @param  string  $message
     * @param  \Exception  $exception
     * @return void
     */
    protected function logError($message, \Exception $exception)
    {
        Log::channel(config('zatca.log_channel', 'zatca'))->error($message, [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'environment' => $this->isSandboxMode() ? 'sandbox' : 'production',
        ]);
    }
}