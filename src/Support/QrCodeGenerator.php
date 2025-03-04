<?php

namespace KhaledHajSalem\ZatcaPhase2\Support;

use Illuminate\Support\Facades\Log;
use KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;

class QrCodeGenerator
{
    /**
     * Generate a QR code for an invoice or credit note.
     *
     * @param  mixed  $document  Invoice or credit note
     * @return string  Base64 encoded QR code image
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public static function generate($document)
    {
        try {
            // Generate TLV (Tag-Length-Value) data for QR code according to ZATCA requirements
            $tlvData = self::generateTLVData($document);

            // Generate QR code as base64 image
            $qrCode = QrCode::format('png')
                ->size(200)
                ->margin(1)
                ->errorCorrection('M')
                ->generate($tlvData);

            return 'data:image/png;base64,' . base64_encode($qrCode);
        } catch (\Exception $e) {
            Log::channel(config('zatca.log_channel', 'zatca'))->error('QR code generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'document_id' => $document->id ?? 'unknown',
            ]);

            throw new ZatcaException('Failed to generate QR code: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate TLV (Tag-Length-Value) data for QR code.
     *
     * @param  mixed  $document  Invoice or credit note
     * @return string  Binary TLV data
     */
    protected static function generateTLVData($document)
    {
        // Get the invoice service to access helper methods
        $invoiceService = app('zatca.invoice');

        // Get the field mappings
        $mappings = config('zatca.field_mapping');

        // Determine if document is a credit note
        $isCreditNote = $invoiceService->isCreditNote($document);

        // Extract required fields
        $sellerName = $invoiceService->getFieldValue($document, $mappings['seller_name']) ?? config('zatca.organization.name');
        $sellerTaxNumber = $invoiceService->getFieldValue($document, $mappings['seller_tax_number']) ?? config('zatca.organization.tax_number');

        // Get invoice date
        $invoiceDate = $invoiceService->getFieldValue($document, $mappings['issue_date']);
        if (is_object($invoiceDate) && method_exists($invoiceDate, 'format')) {
            $invoiceDate = $invoiceDate->format('Y-m-d\TH:i:s\Z');
        } elseif (is_string($invoiceDate)) {
            $invoiceDate = Carbon::parse($invoiceDate)->format('Y-m-d\TH:i:s\Z');
        } else {
            $invoiceDate = Carbon::now()->format('Y-m-d\TH:i:s\Z');
        }

        // Get invoice total and VAT amount
        $invoiceTotal = $invoiceService->getFieldValue($document, $mappings['total_including_vat']);
        $vatTotal = $invoiceService->getFieldValue($document, $mappings['total_vat']);

        // For credit notes, ensure the amounts are positive in the QR code
        // (The negative sign is already shown in the document itself)
        if ($isCreditNote) {
            $invoiceTotal = abs($invoiceTotal);
            $vatTotal = abs($vatTotal);
        }

        // Generate TLV (Tag-Length-Value) data
        $tlvData = '';

        // Tag 1: Seller Name (seller name)
        $tlvData .= self::tlvEncode(1, $sellerName);

        // Tag 2: VAT Registration Number (seller tax number)
        $tlvData .= self::tlvEncode(2, $sellerTaxNumber);

        // Tag 3: Invoice Date and Time (timestamp)
        $tlvData .= self::tlvEncode(3, $invoiceDate);

        // Tag 4: Invoice Total (with VAT)
        $tlvData .= self::tlvEncode(4, number_format($invoiceTotal, 2, '.', ''));

        // Tag 5: VAT Total
        $tlvData .= self::tlvEncode(5, number_format($vatTotal, 2, '.', ''));

        // Tag 6: Document Hash (if available)
        if (!empty($document->zatca_invoice_hash)) {
            $tlvData .= self::tlvEncode(6, $document->zatca_invoice_hash);
        }

        // Tag 7: Digital Signature (if available)
        if (!empty($document->zatca_signature)) {
            $tlvData .= self::tlvEncode(7, $document->zatca_signature);
        }

        // Tag 8: Document Type - For Credit Notes
        if ($isCreditNote) {
            $tlvData .= self::tlvEncode(8, 'CreditNote');

            // Tag 9: Original Invoice Reference (for credit notes)
            $creditNoteConfig = config('zatca.credit_note.invoice_reference');
            $originalInvoiceNumberField = $creditNoteConfig['number_reference'] ?? 'originalInvoice.number';
            $originalInvoiceNumber = $invoiceService->getFieldValue($document, $originalInvoiceNumberField);

            if ($originalInvoiceNumber) {
                $tlvData .= self::tlvEncode(9, $originalInvoiceNumber);
            }
        }

        return $tlvData;
    }

    /**
     * Encode a value in TLV (Tag-Length-Value) format.
     *
     * @param  int     $tag    Tag identifier (1-byte)
     * @param  string  $value  Value to encode
     * @return string  Binary TLV data
     */
    protected static function tlvEncode($tag, $value)
    {
        // Convert tag to binary (1 byte)
        $tagBin = pack('C', $tag);

        // Convert value to UTF-8 binary
        $valueBin = is_numeric($value) ? (string) $value : $value;

        // Get length of value in bytes
        $length = strlen($valueBin);

        // Convert length to binary (1 byte)
        $lengthBin = pack('C', $length);

        // Combine tag, length, and value
        return $tagBin . $lengthBin . $valueBin;
    }
}