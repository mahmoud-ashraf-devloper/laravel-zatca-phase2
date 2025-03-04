<?php

namespace KhaledHajSalem\ZatcaPhase2\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException;
use KhaledHajSalem\ZatcaPhase2\Services\DocumentPdfService;

class ZatcaDocumentController extends Controller
{
    /**
     * Download PDF for a document.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function downloadPdf(Request $request, $id)
    {
        try {
            // Get document
            $document = $this->findDocument($id);

            if (!$document) {
                return response()->json(['message' => 'Document not found'], 404);
            }

            // Generate PDF
            $pdfService = app(DocumentPdfService::class);
            $options = $request->only(['paper', 'orientation', 'template', 'custom_css']);
            $pdf = $pdfService->generatePdf($document, $options);

            // Determine filename based on document type
            $invoiceService = app('zatca.invoice');
            $isCreditNote = $invoiceService->isCreditNote($document);
            $prefix = $isCreditNote ? 'credit-note' : 'invoice';
            $number = $invoiceService->getFieldValue($document, config('zatca.field_mapping.invoice_number'));
            $filename = $prefix . '-' . $number . '.pdf';

            return response($pdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', $request->get('inline', true) ? 'inline' : 'attachment; filename="' . $filename . '"');
        } catch (ZatcaException $e) {
            Log::channel(config('zatca.log_channel', 'zatca'))->error('Error generating PDF', [
                'error' => $e->getMessage(),
                'document_id' => $id,
            ]);

            return response()->json(['message' => 'Error generating PDF: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::channel(config('zatca.log_channel', 'zatca'))->error('Unexpected error generating PDF', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'document_id' => $id,
            ]);

            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    /**
     * Download QR code for a document.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function downloadQr(Request $request, $id)
    {
        try {
            // Get document
            $document = $this->findDocument($id);

            if (!$document) {
                return response()->json(['message' => 'Document not found'], 404);
            }

            // Get QR code
            $qrCode = $document->zatca_qr_code;

            if (empty($qrCode)) {
                // Generate QR code if not already stored
                $qrCode = app('zatca.invoice')->generateQrCode($document);
                $document->zatca_qr_code = $qrCode;
                $document->save();
            }

            // Extract the base64 image data without the data URI prefix
            $qrImage = str_replace('data:image/png;base64,', '', $qrCode);
            $qrImage = base64_decode($qrImage);

            // Determine filename
            $invoiceService = app('zatca.invoice');
            $number = $invoiceService->getFieldValue($document, config('zatca.field_mapping.invoice_number'));
            $filename = 'qr-' . $number . '.png';

            return response($qrImage)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (ZatcaException $e) {
            Log::channel(config('zatca.log_channel', 'zatca'))->error('Error generating QR code', [
                'error' => $e->getMessage(),
                'document_id' => $id,
            ]);

            return response()->json(['message' => 'Error generating QR code: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::channel(config('zatca.log_channel', 'zatca'))->error('Unexpected error generating QR code', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'document_id' => $id,
            ]);

            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    /**
     * Download XML for a document.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function downloadXml(Request $request, $id)
    {
        try {
            // Get document
            $document = $this->findDocument($id);

            if (!$document) {
                return response()->json(['message' => 'Document not found'], 404);
            }

            // Get XML
            $xml = $document->zatca_xml;

            if (empty($xml)) {
                return response()->json(['message' => 'XML not found for this document'], 404);
            }

            // Determine filename
            $invoiceService = app('zatca.invoice');
            $isCreditNote = $invoiceService->isCreditNote($document);
            $prefix = $isCreditNote ? 'credit-note' : 'invoice';
            $number = $invoiceService->getFieldValue($document, config('zatca.field_mapping.invoice_number'));
            $filename = $prefix . '-' . $number . '.xml';

            return response($xml)
                ->header('Content-Type', 'text/xml')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Exception $e) {
            Log::channel(config('zatca.log_channel', 'zatca'))->error('Error downloading XML', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'document_id' => $id,
            ]);

            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    /**
     * Find the document by ID.
     *
     * @param  int  $id
     * @return mixed|null
     */
    protected function findDocument($id)
    {
        $invoiceModel = config('zatca.invoice_model');
        $creditNoteModel = config('zatca.credit_note_model');

        // First try to find in invoice model
        $document = $invoiceModel::find($id);

        // If not found and credit note model is different, try there
        if (!$document && $creditNoteModel !== $invoiceModel) {
            $document = $creditNoteModel::find($id);
        }

        return $document;
    }
}