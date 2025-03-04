<?php

namespace KhaledHajSalem\ZatcaPhase2\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException;
use KhaledHajSalem\ZatcaPhase2\Support\XmlGenerator;
use Ramsey\Uuid\Uuid;

class InvoiceService
{
    /**
     * Generate XML for an invoice or credit note.
     *
     * @param  mixed  $document  Invoice or credit note
     * @return string
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public function generateXml($document)
    {
        try {
            // Determine if document is a credit note
            $isCreditNote = $this->isCreditNote($document);

            // Map document data to ZATCA format using field mappings
            $mappedData = $this->mapDocumentData($document, $isCreditNote);

            // Generate the XML
            $xml = XmlGenerator::generate($mappedData, $isCreditNote);

            return $xml;
        } catch (\Exception $e) {
            Log::channel(config('zatca.log_channel', 'zatca'))->error('XML generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'document_id' => $document->id ?? 'unknown',
                'document_type' => $isCreditNote ? 'credit_note' : 'invoice',
            ]);

            throw new ZatcaException('Failed to generate document XML: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Determine if a document is a credit note.
     *
     * @param  mixed  $document
     * @return bool
     */
    public function isCreditNote($document)
    {
        $creditNoteConfig = config('zatca.credit_note.identification');
        $method = $creditNoteConfig['method'] ?? 'type_field';

        switch ($method) {
            case 'model':
                // Check if document is an instance of the credit note model
                $creditNoteModel = config('zatca.credit_note_model');
                return $document instanceof $creditNoteModel;

            case 'type_field':
                // Check if document has type field matching credit note type
                $typeField = $creditNoteConfig['type_field'] ?? 'type';
                $typeValue = $creditNoteConfig['type_value'] ?? 'credit_note';

                return isset($document->{$typeField}) && $document->{$typeField} === $typeValue;

            case 'table':
                // Check if document comes from credit note table
                // This would require additional context we don't have in this method
                // so we'll default to false
                return false;

            default:
                return false;
        }
    }

    /**
     * Generate a UUID for an invoice.
     *
     * @return string
     */
    public function generateUuid()
    {
        return (string) Uuid::uuid4();
    }

    /**
     * Generate a hash for an invoice.
     *
     * @param  mixed  $invoice
     * @return string
     */
    public function generateHash($invoice)
    {
        // In a real implementation, you would generate a hash according to ZATCA requirements
        // This is a simplified example
        $data = json_encode([
            'id' => $invoice->id,
            'number' => $invoice->number ?? $invoice->id,
            'date' => $invoice->created_at?->format('Y-m-d\TH:i:s\Z') ?? now()->format('Y-m-d\TH:i:s\Z'),
            'total' => $invoice->total ?? 0,
        ]);

        return hash('sha256', $data);
    }

    /**
     * Map document data to ZATCA format using field mappings.
     *
     * @param  mixed  $document  Invoice or credit note
     * @param  bool  $isCreditNote
     * @return array
     */
    protected function mapDocumentData($document, $isCreditNote = false)
    {
        $mappings = config('zatca.field_mapping');
        $result = [];

        // Basic document information
        $result['invoice_number'] = $this->getFieldValue($document, $mappings['invoice_number']);

        // Set the document type code based on whether it's a credit note
        if ($isCreditNote) {
            $result['invoice_type'] = $this->getFieldValue($document, $mappings['invoice_type'], '381'); // Default to credit note

            // Add billing reference for credit notes (reference to original invoice)
            $creditNoteConfig = config('zatca.credit_note.invoice_reference');
            $referenceField = $creditNoteConfig['field'] ?? 'original_invoice_id';
            $numberReference = $creditNoteConfig['number_reference'] ?? 'originalInvoice.number';
            $uuidReference = $creditNoteConfig['uuid_reference'] ?? 'originalInvoice.zatca_invoice_uuid';

            $result['billing_reference_id'] = $this->getFieldValue($document, $numberReference);
            $result['billing_reference_uuid'] = $this->getFieldValue($document, $uuidReference);
            $result['billing_reference_date'] = $this->getFieldValue($document, 'originalInvoice.issue_date');
        } else {
            $result['invoice_type'] = $this->getFieldValue($document, $mappings['invoice_type'], '388'); // Default to standard invoice
        }
        $result['issue_date'] = $this->getFieldValue($document, $mappings['issue_date']);

        if (is_object($result['issue_date']) && method_exists($result['issue_date'], 'format')) {
            $result['issue_date'] = $result['issue_date']->format('Y-m-d');
        }

        // Issue time - can be derived from issue_date if it's a DateTime
        $issueTime = $this->getFieldValue($document, $mappings['issue_time']);
        if (is_object($issueTime) && method_exists($issueTime, 'format')) {
            $result['issue_time'] = $issueTime->format('H:i:s');
        } else {
            $result['issue_time'] = $issueTime ?? date('H:i:s');
        }

        $result['invoice_currency_code'] = $this->getFieldValue($document, $mappings['invoice_currency_code'], 'SAR');
        $result['invoice_counter_value'] = $this->getFieldValue($document, $mappings['invoice_counter_value']);

        // Seller information
        $result['seller_name'] = $this->getFieldValue($document, $mappings['seller_name']) ?? config('zatca.organization.name');
        $result['seller_tax_number'] = $this->getFieldValue($document, $mappings['seller_tax_number']) ?? config('zatca.organization.tax_number');
        $result['seller_address'] = $this->getFieldValue($document, $mappings['seller_address']);
        $result['seller_street'] = $this->getFieldValue($document, $mappings['seller_street']);
        $result['seller_building_number'] = $this->getFieldValue($document, $mappings['seller_building_number']);
        $result['seller_postal_code'] = $this->getFieldValue($document, $mappings['seller_postal_code']);
        $result['seller_city'] = $this->getFieldValue($document, $mappings['seller_city']);
        $result['seller_district'] = $this->getFieldValue($document, $mappings['seller_district']);
        $result['seller_additional_number'] = $this->getFieldValue($document, $mappings['seller_additional_number']);
        $result['seller_country_code'] = $this->getFieldValue($document, $mappings['seller_country_code'], 'SA');

        // Buyer information
        $result['buyer_name'] = $this->getFieldValue($document, $mappings['buyer_name']);
        $result['buyer_tax_number'] = $this->getFieldValue($document, $mappings['buyer_tax_number']);
        $result['buyer_address'] = $this->getFieldValue($document, $mappings['buyer_address']);
        $result['buyer_street'] = $this->getFieldValue($document, $mappings['buyer_street']);
        $result['buyer_building_number'] = $this->getFieldValue($document, $mappings['buyer_building_number']);
        $result['buyer_postal_code'] = $this->getFieldValue($document, $mappings['buyer_postal_code']);
        $result['buyer_city'] = $this->getFieldValue($document, $mappings['buyer_city']);
        $result['buyer_district'] = $this->getFieldValue($document, $mappings['buyer_district']);
        $result['buyer_additional_number'] = $this->getFieldValue($document, $mappings['buyer_additional_number']);
        $result['buyer_country_code'] = $this->getFieldValue($document, $mappings['buyer_country_code'], 'SA');

        // Line items
        $result['line_items'] = [];
        $lineItems = $this->getFieldValue($document, $mappings['line_items']) ?? [];

        foreach ($lineItems as $item) {
            $lineItem = [
                'name' => $this->getFieldValue($item, $mappings['item_name']),
                'quantity' => $this->getFieldValue($item, $mappings['item_quantity']),
                'unit_code' => $this->getFieldValue($item, $mappings['item_unit_code'], 'EA'),
                'price' => $this->getFieldValue($item, $mappings['item_price']),
                'price_inclusive' => $this->getFieldValue($item, $mappings['item_price_inclusive']),
                'discount' => $this->getFieldValue($item, $mappings['item_discount'], 0),
                'discount_reason' => $this->getFieldValue($item, $mappings['item_discount_reason']),
                'tax_category' => $this->getFieldValue($item, $mappings['item_tax_category'], 'S'),
                'tax_rate' => $this->getFieldValue($item, $mappings['item_tax_rate'], 15),
                'tax_amount' => $this->getFieldValue($item, $mappings['item_tax_amount']),
            ];

            $result['line_items'][] = $lineItem;
        }

        // Summary information
        $result['total_excluding_vat'] = $this->getFieldValue($document, $mappings['total_excluding_vat']);
        $result['total_including_vat'] = $this->getFieldValue($document, $mappings['total_including_vat']);
        $result['total_vat'] = $this->getFieldValue($document, $mappings['total_vat']);
        $result['total_discount'] = $this->getFieldValue($document, $mappings['total_discount'], 0);

        // Additional fields
        $result['supply_date'] = $this->getFieldValue($document, $mappings['supply_date']);
        $result['supply_end_date'] = $this->getFieldValue($document, $mappings['supply_end_date']);
        $result['special_tax_treatment'] = $this->getFieldValue($document, $mappings['special_tax_treatment']);
        $result['invoice_note'] = $this->getFieldValue($document, $mappings['invoice_note']);

        // Custom fields
        $customFields = $this->getFieldValue($document, $mappings['custom_fields']) ?? [];
        $result['custom_fields'] = $customFields;

        return $result;
    }

    /**
     * Get a field value from an object using a field path.
     *
     * @param  mixed  $object
     * @param  string|null  $fieldPath
     * @param  mixed  $default
     * @return mixed
     */
    public function getFieldValue($object, $fieldPath, $default = null)
    {
        if (empty($fieldPath)) {
            return $default;
        }

        // Handle nested fields (e.g., 'customer.name')
        $parts = explode('.', $fieldPath);
        $value = $object;

        foreach ($parts as $part) {
            if (is_object($value)) {
                // Try object property or method
                if (isset($value->{$part})) {
                    $value = $value->{$part};
                } elseif (method_exists($value, $part)) {
                    $value = $value->{$part}();
                } elseif (method_exists($value, 'get' . ucfirst($part))) {
                    $method = 'get' . ucfirst($part);
                    $value = $value->{$method}();
                } else {
                    return $default;
                }
            } elseif (is_array($value) && isset($value[$part])) {
                // Try array key
                $value = $value[$part];
            } else {
                return $default;
            }
        }

        return $value;
    }
}