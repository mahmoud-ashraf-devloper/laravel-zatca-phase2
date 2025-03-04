<?php

namespace KhaledHajSalem\ZatcaPhase2\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException;
use KhaledHajSalem\ZatcaPhase2\Services\InvoiceService;
use KhaledHajSalem\ZatcaPhase2\Services\ZatcaService;

class CheckZatcaStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zatca:check-status 
                            {--model= : Specific model to check (invoice or credit_note)}
                            {--limit=50 : Maximum number of documents to check}
                            {--status=submitted : Status to filter by}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check status of submitted documents with ZATCA';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param \KhaledHajSalem\ZatcaPhase2\Services\ZatcaService $zatcaService
     * @return int
     */
    public function handle(ZatcaService $zatcaService, InvoiceService $invoiceService)
    {
        $model = $this->option('model');
        $limit = (int) $this->option('limit');
        $status = $this->option('status');

        $this->info('Checking status of ZATCA submitted documents...');

        // Determine which models to check
        if ($model === 'invoice') {
            $invoiceModel = config('zatca.invoice_model');
            $documents = $invoiceModel::where('zatca_status', $status)
                ->limit($limit)
                ->get();

            $this->info("Found {$documents->count()} invoices with status '{$status}'");
        } elseif ($model === 'credit_note') {
            $creditNoteModel = config('zatca.credit_note_model');

            // If credit note model is the same as invoice model, we need to filter by type
            if ($creditNoteModel === config('zatca.invoice_model')) {
                $creditNoteConfig = config('zatca.credit_note.identification');
                $method = $creditNoteConfig['method'] ?? 'type_field';

                if ($method === 'type_field') {
                    $typeField = $creditNoteConfig['type_field'] ?? 'type';
                    $typeValue = $creditNoteConfig['type_value'] ?? 'credit_note';

                    $documents = $creditNoteModel::where('zatca_status', $status)
                        ->where($typeField, $typeValue)
                        ->limit($limit)
                        ->get();
                } else {
                    // Other identification methods would need specific implementations
                    $this->error("Unsupported credit note identification method: {$method}");
                    return 1;
                }
            } else {
                $documents = $creditNoteModel::where('zatca_status', $status)
                    ->limit($limit)
                    ->get();
            }

            $this->info("Found {$documents->count()} credit notes with status '{$status}'");
        } else {
            // Check both invoices and credit notes
            $invoiceModel = config('zatca.invoice_model');
            $documents = $invoiceModel::where('zatca_status', $status)
                ->limit($limit)
                ->get();

            $this->info("Found {$documents->count()} documents with status '{$status}'");
        }

        $bar = $this->output->createProgressBar($documents->count());
        $bar->start();

        $updated = 0;
        $errors = 0;

        foreach ($documents as $document) {
            $documentType = $invoiceService->isCreditNote($document) ? 'credit note' : 'invoice';
            $documentId = $document->id;

            try {
                // Extract request ID from response
                $response = null;
                if (is_string($document->zatca_response)) {
                    $response = json_decode($document->zatca_response, true);
                } elseif (is_array($document->zatca_response)) {
                    $response = $document->zatca_response;
                }

                $requestId = $response['requestID'] ?? null;

                if (!$requestId) {
                    $this->warn(" No request ID found for {$documentType} #{$documentId}");
                    $errors++;
                    continue;
                }

                // Check status with ZATCA
                $statusResponse = $zatcaService->checkInvoiceStatus($document);

                // Update document status
                $newStatus = $statusResponse['status'] ?? 'unknown';

                if ($newStatus !== $document->zatca_status) {
                    $document->zatca_status = $newStatus;
                    $document->save();
                    $updated++;
                }
            } catch (ZatcaException $e) {
                $this->error(" Error checking {$documentType} #{$documentId}: {$e->getMessage()}");
                Log::channel(config('zatca.log_channel', 'zatca'))->error("Status check failed", [
                    'document_id' => $documentId,
                    'document_type' => $documentType,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            } catch (\Exception $e) {
                $this->error(" Unexpected error with {$documentType} #{$documentId}: {$e->getMessage()}");
                Log::channel(config('zatca.log_channel', 'zatca'))->error("Status check failed with unexpected error", [
                    'document_id' => $documentId,
                    'document_type' => $documentType,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Status check completed!");
        $this->info("- Documents checked: " . $documents->count());
        $this->info("- Status updated: {$updated}");
        $this->info("- Errors encountered: {$errors}");

        return 0;
    }
}