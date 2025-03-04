<?php

namespace KhaledHajSalem\ZatcaPhase2\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batch;
use Throwable;

class ProcessZatcaBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The collection of documents to process.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $documents;

    /**
     * The operation to perform.
     *
     * @var string
     */
    protected $operation;

    /**
     * The batch size for processing.
     *
     * @var int
     */
    protected $batchSize;

    /**
     * The callback to execute after processing.
     *
     * @var callable|null
     */
    protected $callback;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1; // The batch handling already has its own retry logic

    /**
     * Create a new job instance.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|array  $documents  Collection of invoices or credit notes
     * @param  string  $operation   The operation to perform (report or clear)
     * @param  int     $batchSize   Number of documents to process in each batch
     * @param  callable|null $callback  Callback to execute after batch is complete
     * @return void
     */
    public function __construct($documents, string $operation = 'report', int $batchSize = 10, ?callable $callback = null)
    {
        // Convert array to collection if needed
        if (is_array($documents)) {
            $documents = collect($documents);
        }

        $this->documents = $documents;
        $this->operation = strtolower($operation);
        $this->batchSize = max(1, $batchSize); // Ensure batch size is at least 1
        $this->callback = $callback;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $count = $this->documents->count();
        $operation = $this->operation;

        Log::channel(config('zatca.log_channel', 'zatca'))->info(
            "Starting ZATCA batch processing for {$count} documents with operation: {$operation}"
        );

        // Create a job for each document
        $jobs = $this->documents->map(function ($document) use ($operation) {
            return new ProcessZatcaDocument($document, $operation);
        })->toArray();

        // If no jobs, log and return
        if (empty($jobs)) {
            Log::channel(config('zatca.log_channel', 'zatca'))->info(
                "No documents to process in ZATCA batch"
            );
            return;
        }

        try {
            // Laravel 8+ batching
            if (method_exists(Bus::class, 'batch')) {
                // Create a batch
                $batch = Bus::batch(array_chunk($jobs, $this->batchSize))
                    ->then(function (Batch $batch) {
                        $this->handleBatchSuccess($batch);
                    })
                    ->catch(function (Batch $batch, Throwable $e) {
                        $this->handleBatchFailure($batch, $e);
                    })
                    ->allowFailures()  // Continue processing even if some jobs fail
                    ->dispatch();

                Log::channel(config('zatca.log_channel', 'zatca'))->info(
                    "ZATCA batch queued with ID: {$batch->id}",
                    ['total_jobs' => $batch->totalJobs]
                );
            } else {
                // Laravel 7 or older - dispatch jobs individually
                Log::channel(config('zatca.log_channel', 'zatca'))->warning(
                    "Laravel batching not available, dispatching jobs individually"
                );

                foreach ($jobs as $job) {
                    dispatch($job);
                }

                // Immediately call the callback as we can't track batch completion
                if (is_callable($this->callback)) {
                    call_user_func($this->callback, null);
                }
            }
        } catch (Throwable $e) {
            Log::channel(config('zatca.log_channel', 'zatca'))->error(
                "Failed to create ZATCA batch job",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            // Call the callback with the exception
            if (is_callable($this->callback)) {
                call_user_func($this->callback, null, $e);
            }

            throw $e;
        }
    }

    /**
     * Handle successful batch completion.
     *
     * @param  \Illuminate\Bus\Batch  $batch
     * @return void
     */
    protected function handleBatchSuccess(Batch $batch)
    {
        Log::channel(config('zatca.log_channel', 'zatca'))->info(
            "ZATCA batch processing completed",
            [
                'batch_id' => $batch->id,
                'processed' => $batch->processedJobs(),
                'failed' => $batch->failedJobs,
                'total' => $batch->totalJobs,
            ]
        );

        // Execute callback
        if (is_callable($this->callback)) {
            call_user_func($this->callback, $batch);
        }
    }

    /**
     * Handle batch failure.
     *
     * @param  \Illuminate\Bus\Batch  $batch
     * @param  \Throwable  $exception
     * @return void
     */
    protected function handleBatchFailure(Batch $batch, Throwable $exception)
    {
        Log::channel(config('zatca.log_channel', 'zatca'))->error(
            "ZATCA batch processing encountered errors",
            [
                'batch_id' => $batch->id,
                'processed' => $batch->processedJobs(),
                'failed' => $batch->failedJobs,
                'total' => $batch->totalJobs,
                'error' => $exception->getMessage(),
            ]
        );

        // Execute callback with the exception
        if (is_callable($this->callback)) {
            call_user_func($this->callback, $batch, $exception);
        }
    }
}