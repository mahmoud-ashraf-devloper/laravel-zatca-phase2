<?php

namespace KhaledHajSalem\ZatcaPhase2\Traits;

trait HasZatcaSupport
{
    /**
     * Initialize the trait.
     *
     * @return void
     */
    public function initializeHasZatcaSupport()
    {
        // Add JSON casting for ZATCA response and errors fields
        $this->casts['zatca_response'] = 'array';
        $this->casts['zatca_errors'] = 'array';
    }

    /**
     * Scope a query to only include invoices that need reporting to ZATCA.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNeedZatcaReporting($query)
    {
        return $query->whereNull('zatca_reported_at')
            ->whereNull('zatca_cleared_at')
            ->whereNull('zatca_status')
            ->orWhere('zatca_status', 'FAILED');
    }

    /**
     * Scope a query to only include invoices that need clearance with ZATCA.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNeedZatcaClearance($query)
    {
        $threshold = config('zatca.clearance_threshold', 1000);

        return $query->whereNull('zatca_cleared_at')
            ->whereNull('zatca_status')
            ->orWhere('zatca_status', 'FAILED')
            ->where('total', '>=', $threshold);
    }

    /**
     * Determine if the invoice has been reported to ZATCA.
     *
     * @return bool
     */
    public function isZatcaReported()
    {
        return !is_null($this->zatca_reported_at);
    }

    /**
     * Determine if the invoice has been cleared by ZATCA.
     *
     * @return bool
     */
    public function isZatcaCleared()
    {
        return !is_null($this->zatca_cleared_at);
    }

    /**
     * Mark the invoice as reported to ZATCA.
     *
     * @param  array  $response
     * @return $this
     */
    public function markAsZatcaReported($response = [])
    {
        $this->zatca_reported_at = now();
        $this->zatca_status = 'REPORTED';
        $this->zatca_response = $response;
        $this->save();

        return $this;
    }

    /**
     * Mark the invoice as cleared by ZATCA.
     *
     * @param  array  $response
     * @return $this
     */
    public function markAsZatcaCleared($response = [])
    {
        $this->zatca_cleared_at = now();
        $this->zatca_status = 'CLEARED';
        $this->zatca_response = $response;
        $this->save();

        return $this;
    }

    /**
     * Mark the invoice as having a ZATCA failure.
     *
     * @param  array  $errors
     * @return $this
     */
    public function markAsZatcaFailed($errors = [])
    {
        $this->zatca_status = 'FAILED';
        $this->zatca_errors = $errors;
        $this->save();

        return $this;
    }

    /**
     * Get the ZATCA invoice UUID.
     *
     * @return string|null
     */
    public function getZatcaUuid()
    {
        return $this->zatca_invoice_uuid;
    }

    /**
     * Get the ZATCA invoice hash.
     *
     * @return string|null
     */
    public function getZatcaHash()
    {
        return $this->zatca_invoice_hash;
    }

    /**
     * Get the ZATCA QR code.
     *
     * @return string|null
     */
    public function getZatcaQrCode()
    {
        return $this->zatca_qr_code;
    }

    /**
     * Get the ZATCA XML.
     *
     * @return string|null
     */
    public function getZatcaXml()
    {
        return $this->zatca_xml;
    }

    /**
     * Get the ZATCA status.
     *
     * @return string|null
     */
    public function getZatcaStatus()
    {
        return $this->zatca_status;
    }

    /**
     * Determine if this document is a credit note.
     *
     * @return bool
     */
    public function isZatcaCreditNote()
    {
        $creditNoteConfig = config('zatca.credit_note.identification');
        $method = $creditNoteConfig['method'] ?? 'type_field';

        if ($method === 'type_field') {
            $typeField = $creditNoteConfig['type_field'] ?? 'type';
            $typeValue = $creditNoteConfig['type_value'] ?? 'credit_note';

            return isset($this->{$typeField}) && $this->{$typeField} === $typeValue;
        }

        if ($method === 'model') {
            $creditNoteModel = config('zatca.credit_note_model');
            return $this instanceof $creditNoteModel;
        }

        return false;
    }

    /**
     * Get the original invoice reference for a credit note.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getZatcaOriginalInvoice()
    {
        if (!$this->isZatcaCreditNote()) {
            return null;
        }

        $creditNoteConfig = config('zatca.credit_note.invoice_reference');
        $referenceField = $creditNoteConfig['field'] ?? 'original_invoice_id';

        if (empty($this->{$referenceField})) {
            return null;
        }

        $invoiceModel = config('zatca.invoice_model');
        return $invoiceModel::find($this->{$referenceField});
    }

    /**
     * Scope a query to only include credit notes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeZatcaCreditNotes($query)
    {
        $creditNoteConfig = config('zatca.credit_note.identification');
        $method = $creditNoteConfig['method'] ?? 'type_field';

        if ($method === 'type_field') {
            $typeField = $creditNoteConfig['type_field'] ?? 'type';
            $typeValue = $creditNoteConfig['type_value'] ?? 'credit_note';

            return $query->where($typeField, $typeValue);
        }

        return $query; // For other methods, filtering would need to be handled differently
    }

    /**
     * Scope a query to only include invoices (non-credit notes).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeZatcaInvoices($query)
    {
        $creditNoteConfig = config('zatca.credit_note.identification');
        $method = $creditNoteConfig['method'] ?? 'type_field';

        if ($method === 'type_field') {
            $typeField = $creditNoteConfig['type_field'] ?? 'type';
            $typeValue = $creditNoteConfig['type_value'] ?? 'credit_note';

            return $query->where($typeField, '!=', $typeValue);
        }

        return $query; // For other methods, filtering would need to be handled differently
    }

    /**
     * Scope a query to only include credit notes that need reporting to ZATCA.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNeedZatcaCreditNoteReporting($query)
    {
        return $query->zatcaCreditNotes()->needZatcaReporting();
    }

    /**
     * Scope a query to only include credit notes that need clearance with ZATCA.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNeedZatcaCreditNoteClearance($query)
    {
        return $query->zatcaCreditNotes()->needZatcaClearance();
    }
}