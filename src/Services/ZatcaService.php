<?php

namespace KhaledHajSalem\ZatcaPhase2\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException;
use KhaledHajSalem\ZatcaPhase2\Support\QrCodeGenerator;
use KhaledHajSalem\ZatcaPhase2\Support\XmlGenerator;

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
    protected $client;

    /**
     * ZATCA base API URL.
     *
     * @var string
     */
    protected $baseUrl;

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
        $this->baseUrl = config('zatca.api.base_url');
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'verify' => true, // Set to false for testing if needed
        ]);
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
            $url = config('zatca.api.status_url');

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
     * Submit a document to ZATCA for reporting or clearance.
     *
     * @param  mixed  $document  Invoice or credit note
     * @param  string  $type  Either 'reporting' or 'clearance'
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function submitDocument($document, $type)
    {
        $endpoint = $type === 'reporting'
            ? config('zatca.api.reporting_url')
            : config('zatca.api.clearance_url');

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

        $response = $this->client->request('POST', $endpoint, [
            'headers' => $headers,
            'json' => $payload,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Submit an invoice to ZATCA for reporting or clearance.
     *
     * @param  mixed  $invoice
     * @param  string  $type  Either 'reporting' or 'clearance'
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function submitInvoice($invoice, $type)
    {
        return $this->submitDocument($invoice, $type);
    }

    /**
     * Get ZATCA API authentication headers.
     *
     * @return array
     */
    protected function getAuthHeaders()
    {
        // Get the certificate data
        $certificateData = $this->certificateService->getCertificateData();

        // In a real implementation, we would generate proper auth headers
        // based on the certificate and ZATCA requirements
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($certificateData['certificate_id'] . ':' . config('zatca.pih')),
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
        ]);
    }
}