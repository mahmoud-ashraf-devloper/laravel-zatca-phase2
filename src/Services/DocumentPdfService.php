<?php

namespace KhaledHajSalem\ZatcaPhase2\Services;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;
use KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException;
use KhaledHajSalem\ZatcaPhase2\Support\QrCodeGenerator;
use Barryvdh\DomPDF\Facade\Pdf;

class DocumentPdfService
{
    /**
     * Generate a PDF for an invoice or credit note.
     *
     * @param  mixed  $document  Invoice or credit note
     * @param  array  $options   PDF generation options
     * @return string  PDF content
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public function generatePdf($document, array $options = [])
    {
        try {
            // Get the invoice service
            $invoiceService = app('zatca.invoice');

            // Determine if document is a credit note
            $isCreditNote = $invoiceService->isCreditNote($document);

            // Generate or get QR code
            $qrCode = $document->zatca_qr_code ?? QrCodeGenerator::generate($document);

            // Save QR code if it wasn't already stored
            if (empty($document->zatca_qr_code)) {
                $document->zatca_qr_code = $qrCode;
                $document->save();
            }

            // Get mapped document data
            $documentData = $this->getDocumentData($document, $invoiceService);

            // Load items if available
            if (method_exists($document, 'items') && empty($options['skip_items'])) {
                $document->load('items');
            }

            // Determine the view template to use
            $template = $options['template'] ?? ($isCreditNote ? 'zatca::credit_note_pdf' : 'zatca::invoice_pdf');

            // Check if custom view exists
            if (View::exists($template)) {
                // Generate PDF using view
                $pdf = PDF::loadView($template, [
                    'document' => $document,
                    'data' => $documentData,
                    'qrCode' => $qrCode,
                    'options' => $options,
                    'isCreditNote' => $isCreditNote,
                ]);

                // Apply PDF options
                if (!empty($options['paper'])) {
                    $pdf->setPaper($options['paper'], $options['orientation'] ?? 'portrait');
                }

                return $pdf->output();
            } else {
                // Use the default built-in template if custom view doesn't exist
                return $this->generateDefaultPdf($document, $documentData, $qrCode, $isCreditNote, $options);
            }
        } catch (\Exception $e) {
            Log::channel(config('zatca.log_channel', 'zatca'))->error('PDF generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'document_id' => $document->id ?? 'unknown',
            ]);

            throw new ZatcaException('Failed to generate PDF: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate a default PDF without requiring a blade template.
     *
     * @param  mixed   $document     Invoice or credit note
     * @param  array   $data         Mapped document data
     * @param  string  $qrCode       QR code image
     * @param  bool    $isCreditNote Whether the document is a credit note
     * @param  array   $options      PDF options
     * @return string  PDF content
     */
    protected function generateDefaultPdf($document, array $data, $qrCode, $isCreditNote, array $options)
    {
        // Build HTML content for the PDF
        $title = $isCreditNote ? 'CREDIT NOTE' : 'TAX INVOICE';
        $documentType = $isCreditNote ? 'Credit Note' : 'Invoice';

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>' . $title . ' #' . $data['invoice_number'] . '</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    color: #333;
                    line-height: 1.5;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                    padding: 10px;
                    border-bottom: 1px solid #ddd;
                }
                .header h1 {
                    color: ' . ($isCreditNote ? '#d9534f' : '#5cb85c') . ';
                }
                .qr-code {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .qr-code img {
                    max-width: 150px;
                }
                .section {
                    margin-bottom: 20px;
                }
                .section h3 {
                    border-bottom: 1px solid #eee;
                    padding-bottom: 5px;
                    color: #666;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 8px;
                }
                th {
                    background-color: #f8f8f8;
                    text-align: left;
                }
                .total {
                    text-align: right;
                    margin-top: 20px;
                }
                .total table {
                    width: 300px;
                    margin-left: auto;
                }
                .total table td {
                    padding: 5px;
                }
                .footer {
                    margin-top: 50px;
                    font-size: 10px;
                    text-align: center;
                    color: #666;
                    border-top: 1px solid #eee;
                    padding-top: 10px;
                }
                ' . ($options['custom_css'] ?? '') . '
            </style>
        </head>
        <body>
            <div class="header">
                <h1>' . $title . '</h1>
                <p>' . $documentType . ' #: ' . $data['invoice_number'] . '</p>
                <p>Date: ' . $data['issue_date'] . '</p>
                ' . ($isCreditNote && !empty($data['original_invoice_number']) ? '<p>Original Invoice: ' . $data['original_invoice_number'] . '</p>' : '') . '
            </div>
            
            <div class="qr-code">
                <img src="' . $qrCode . '" alt="ZATCA QR Code">
            </div>
            
            <div class="section">
                <div style="width: 48%; float: left;">
                    <h3>From:</h3>
                    <p><strong>' . $data['seller_name'] . '</strong></p>
                    <p>VAT #: ' . $data['seller_tax_number'] . '</p>
                    ' . (!empty($data['seller_address']) ? '<p>' . $data['seller_address'] . '</p>' : '') . '
                </div>
                
                <div style="width: 48%; float: right;">
                    <h3>To:</h3>
                    <p><strong>' . $data['buyer_name'] . '</strong></p>
                    ' . (!empty($data['buyer_tax_number']) ? '<p>VAT #: ' . $data['buyer_tax_number'] . '</p>' : '') . '
                    ' . (!empty($data['buyer_address']) ? '<p>' . $data['buyer_address'] . '</p>' : '') . '
                </div>
                <div style="clear: both;"></div>
            </div>
        ';

        // Add items section if available
        if (isset($document->items) && count($document->items) > 0) {
            $html .= '
            <div class="section">
                <h3>Items:</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Tax Rate</th>
                            <th>Tax Amount</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
            ';

            foreach ($document->items as $item) {
                $html .= '
                        <tr>
                            <td>' . ($item->name ?? 'Item') . '</td>
                            <td>' . ($item->quantity ?? 0) . '</td>
                            <td>' . number_format(($item->unit_price ?? 0), 2) . '</td>
                            <td>' . ($item->tax_rate ?? 15) . '%</td>
                            <td>' . number_format(($item->tax_amount ?? 0), 2) . '</td>
                            <td>' . number_format(($item->total_amount ?? 0), 2) . '</td>
                        </tr>
                ';
            }

            $html .= '
                    </tbody>
                </table>
            </div>
            ';
        }

        // Add totals
        $html .= '
            <div class="total">
                <table>
                    <tr>
                        <td><strong>Subtotal:</strong></td>
                        <td>' . number_format($data['total_excluding_vat'], 2) . ' SAR</td>
                    </tr>
                    <tr>
                        <td><strong>VAT (' . ($data['tax_rate'] ?? 15) . '%):</strong></td>
                        <td>' . number_format($data['total_vat'], 2) . ' SAR</td>
                    </tr>
                    <tr>
                        <td><strong>Total:</strong></td>
                        <td>' . number_format($data['total_including_vat'], 2) . ' SAR</td>
                    </tr>
                </table>
            </div>
            
            <div class="footer">
                <p>This is a system-generated document and is valid without a signature.</p>
                <p>ZATCA Status: ' . ($document->zatca_status ?? 'Not Submitted') . '</p>
                <p>ZATCA Compliance: ' . (($document->isZatcaReported() || $document->isZatcaCleared()) ? 'Compliant' : 'Pending') . '</p>
            </div>
        </body>
        </html>';

        // Generate PDF from HTML
        $pdf = PDF::loadHTML($html);

        // Apply PDF options
        if (!empty($options['paper'])) {
            $pdf->setPaper($options['paper'], $options['orientation'] ?? 'portrait');
        }

        return $pdf->output();
    }

    /**
     * Get mapped data from document.
     *
     * @param  mixed  $document  Invoice or credit note
     * @param  \KhaledHajSalem\ZatcaPhase2\Services\InvoiceService  $invoiceService
     * @return array
     */
    protected function getDocumentData($document, $invoiceService)
    {
        $mappings = config('zatca.field_mapping');
        $isCreditNote = $invoiceService->isCreditNote($document);

        $data = [
            'invoice_number' => $invoiceService->getFieldValue($document, $mappings['invoice_number']),
            'issue_date' => $invoiceService->getFieldValue($document, $mappings['issue_date']),
            'seller_name' => $invoiceService->getFieldValue($document, $mappings['seller_name']) ?? config('zatca.organization.name'),
            'seller_tax_number' => $invoiceService->getFieldValue($document, $mappings['seller_tax_number']) ?? config('zatca.organization.tax_number'),
            'buyer_name' => $invoiceService->getFieldValue($document, $mappings['buyer_name']),
            'buyer_tax_number' => $invoiceService->getFieldValue($document, $mappings['buyer_tax_number']),
            'total_excluding_vat' => abs($invoiceService->getFieldValue($document, $mappings['total_excluding_vat'])),
            'total_including_vat' => abs($invoiceService->getFieldValue($document, $mappings['total_including_vat'])),
            'total_vat' => abs($invoiceService->getFieldValue($document, $mappings['total_vat'])),
        ];

        // Format date if it's an object
        if (is_object($data['issue_date']) && method_exists($data['issue_date'], 'format')) {
            $data['issue_date'] = $data['issue_date']->format('Y-m-d');
        }

        // Add seller address if available
        $data['seller_address'] = $this->formatAddress([
            'street' => $invoiceService->getFieldValue($document, $mappings['seller_street'] ?? null),
            'building' => $invoiceService->getFieldValue($document, $mappings['seller_building_number'] ?? null),
            'city' => $invoiceService->getFieldValue($document, $mappings['seller_city'] ?? null),
            'postal' => $invoiceService->getFieldValue($document, $mappings['seller_postal_code'] ?? null),
            'district' => $invoiceService->getFieldValue($document, $mappings['seller_district'] ?? null),
            'country' => $invoiceService->getFieldValue($document, $mappings['seller_country_code'] ?? null),
        ]);

        // Add buyer address if available
        $data['buyer_address'] = $this->formatAddress([
            'street' => $invoiceService->getFieldValue($document, $mappings['buyer_street'] ?? null),
            'building' => $invoiceService->getFieldValue($document, $mappings['buyer_building_number'] ?? null),
            'city' => $invoiceService->getFieldValue($document, $mappings['buyer_city'] ?? null),
            'postal' => $invoiceService->getFieldValue($document, $mappings['buyer_postal_code'] ?? null),
            'district' => $invoiceService->getFieldValue($document, $mappings['buyer_district'] ?? null),
            'country' => $invoiceService->getFieldValue($document, $mappings['buyer_country_code'] ?? null),
        ]);

        // Add original invoice reference for credit notes
        if ($isCreditNote) {
            $creditNoteConfig = config('zatca.credit_note.invoice_reference');
            $originalInvoiceNumberField = $creditNoteConfig['number_reference'] ?? 'originalInvoice.number';
            $data['original_invoice_number'] = $invoiceService->getFieldValue($document, $originalInvoiceNumberField);
        }

        return $data;
    }

    /**
     * Format an address from components.
     *
     * @param  array  $components  Address components
     * @return string|null
     */
    protected function formatAddress(array $components)
    {
        $parts = [];

        if (!empty($components['building'])) {
            $parts[] = 'Building ' . $components['building'];
        }

        if (!empty($components['street'])) {
            $parts[] = $components['street'];
        }

        if (!empty($components['district'])) {
            $parts[] = $components['district'];
        }

        if (!empty($components['city'])) {
            $parts[] = $components['city'];
        }

        if (!empty($components['postal'])) {
            $parts[] = $components['postal'];
        }

        if (!empty($components['country'])) {
            $parts[] = $components['country'];
        }

        return empty($parts) ? null : implode(', ', $parts);
    }
}