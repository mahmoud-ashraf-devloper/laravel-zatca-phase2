<?php

namespace KhaledHajSalem\ZatcaPhase2\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class ZatcaCallbackController extends Controller
{
    /**
     * Handle the ZATCA callback.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        // Log the callback data
        Log::channel(config('zatca.log_channel', 'zatca'))->info('ZATCA callback received', $request->all());

        // Validate callback
        if (!$request->has('requestID') || !$request->has('status')) {
            return response()->json(['message' => 'Invalid callback data'], 400);
        }

        // Find invoice by request ID
        $requestId = $request->input('requestID');
        $status = $request->input('status');

        // Get the invoice model from config
        $invoiceModelClass = config('zatca.invoice_model');

        // Find the invoice
        $invoice = $invoiceModelClass::whereRaw('JSON_EXTRACT(zatca_response, "$.requestID") = ?', [$requestId])->first();

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        // Update status based on callback
        $invoice->zatca_status = $status;

        // If status indicates success, mark accordingly
        if (in_array(strtoupper($status), ['REPORTED', 'CLEARED'])) {
            $method = 'markAsZatca' . ucfirst(strtolower($status));
            if (method_exists($invoice, $method)) {
                $invoice->{$method}($request->all());
            } else {
                $invoice->save();
            }
        } elseif (in_array(strtoupper($status), ['FAILED', 'REJECTED'])) {
            // Handle failure
            if (method_exists($invoice, 'markAsZatcaFailed')) {
                $invoice->markAsZatcaFailed($request->all());
            } else {
                $invoice->zatca_errors = $request->all();
                $invoice->save();
            }
        } else {
            // For other statuses, just save
            $invoice->save();
        }

        return response()->json(['message' => 'Status updated successfully']);
    }
}