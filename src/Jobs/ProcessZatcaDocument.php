<?php

namespace KhaledHajSalem\ZatcaPhase2\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException;
use KhaledHajSalem\ZatcaPhase2\Services\InvoiceService;
use KhaledHajSalem\ZatcaPhase2\Services\ZatcaService;

class ProcessZatcaDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The document instance.
     *
     * @var mixed
     */
    protected $document;

    /**
     * The operation to perform (report or clear).
     *
     * @var string
     */
    protected $operation;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     *
     * @param  mixed   $document   Invoice or credit note instance
     * @param  string  $operation  The operation to perform (report or clear)
     * @return void
     */
    public function __construct($document, $operation = 'report')
    {
        $this->document = $document;
        $this->operation = strtolower($operation);
    }

    /**
     * Execute the job.
     *
     * @param  \KhaledHajSalem\ZatcaPhase2\Services\ZatcaService  $zatcaService
     * @return void
     */
    public function handle(ZatcaService $zatcaService, InvoiceService $invoiceService)
    {
        $documentId = $this->document->id ?? 'unknown';
        $documentType = $invoiceService->isCreditNote($this->document) ? 'credit_note' : 'invoice';

        Log::channel(config('zatca.log_channel', 'zatca'))->info("Processing ZATCA {$this->operation} for {$documentType} #{$documentId}");

        try {
            if ($this->operation === 'report') {
                $zatcaService->reportDocument($this->document);
                Log::channel(config('zatca.log_channel', 'zatca'))->info("Successfully reported {$documentType} #{$documentId} to ZATCA");
            } elseif ($this->operation === 'clear') {
                $zatcaService->clearDocument($this->document);
                Log::channel(config('zatca.log_channel', 'zatca'))->info("Successfully cleared {$documentType} #{$documentId} with ZATCA");
            } else {
                throw new ZatcaException("Unknown operation: {$this->operation}");
            }
        } catch (ZatcaException $e) {
            Log::channel(config('zatca.log_channel', 'zatca'))->error("ZATCA {$this->operation} failed for {$documentType} #{$documentId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark the document as failed
            if (method_exists($this->document, 'markAsZatcaFailed')) {
                $this->document->markAsZatcaFailed(['error' => $e->getMessage()]);
            }

            throw $e;
        }
    }

    /**
     * The job failed to process.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        $documentId = $this->document->id ?? 'unknown';
        $documentType = app('zatca.invoice')->isCreditNote($this->document) ? 'credit_note' : 'invoice';

        Log::channel(config('zatca.log_channel', 'zatca'))->error("ZATCA job failed for {$documentType} #{$documentId}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Mark the document as failed
        if (method_exists($this->document, 'markAsZatcaFailed')) {
            $this->document->markAsZatcaFailed([
                'error' => $exception->getMessage(),
                'operation' => $this->operation,
                'attempts' => $this->attempts()
            ]);
        }
    }
}