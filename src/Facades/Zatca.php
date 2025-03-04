<?php

namespace KhaledHajSalem\ZatcaPhase2\Facades;

use Illuminate\Support\Facades\Facade;
use KhaledHajSalem\ZatcaPhase2\Jobs\ProcessZatcaDocument;
use KhaledHajSalem\ZatcaPhase2\Jobs\ProcessZatcaBatch;

/**
 * @method static array reportInvoice($invoice)
 * @method static array clearInvoice($invoice)
 * @method static array reportCreditNote($creditNote)
 * @method static array clearCreditNote($creditNote)
 * @method static array checkInvoiceStatus($invoice)
 * @method static bool isCreditNote($document)
 * @method static string generatePdf($document, array $options = [])
 *
 * @see \KhaledHajSalem\ZatcaPhase2\Services\ZatcaService
 */
class Zatca extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'zatca';
    }

    /**
     * Queue a document for processing with ZATCA.
     *
     * @param  mixed   $document   Document to process
     * @param  string  $operation  Operation to perform (report or clear)
     * @param  string  $queue      Queue to use
     * @return \Illuminate\Foundation\Bus\PendingDispatch
     */
    public static function queue($document, $operation = 'report', $queue = null)
    {
        $job = new ProcessZatcaDocument($document, $operation);

        if ($queue) {
            $job->onQueue($queue);
        }

        return $job->dispatch();
    }

    /**
     * Queue a batch of documents for processing with ZATCA.
     *
     * @param  array   $documents   Documents to process
     * @param  string  $operation   Operation to perform (report or clear)
     * @param  int     $batchSize   Number of documents to process in each batch
     * @param  callable|null $callback  Callback to execute after batch is complete
     * @param  string  $queue       Queue to use
     * @return \Illuminate\Foundation\Bus\PendingDispatch
     */
    public static function queueBatch(array $documents, $operation = 'report', $batchSize = 10, $callback = null, $queue = null)
    {
        $job = new ProcessZatcaBatch($documents, $operation, $batchSize, $callback);

        if ($queue) {
            $job->onQueue($queue);
        }

        return $job->dispatch();
    }
}