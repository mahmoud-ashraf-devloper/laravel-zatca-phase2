<?php

namespace KhaledHajSalem\ZatcaPhase2\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use KhaledHajSalem\ZatcaPhase2\Services\CertificateService;

class TestZatcaConnectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zatca:test-connection';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test connection to ZATCA API';

    /**
     * The certificate service instance.
     *
     * @var \KhaledHajSalem\ZatcaPhase2\Services\CertificateService
     */
    protected $certificateService;

    /**
     * Create a new command instance.
     *
     * @param  \KhaledHajSalem\ZatcaPhase2\Services\CertificateService  $certificateService
     * @return void
     */
    public function __construct(CertificateService $certificateService)
    {
        parent::__construct();
        $this->certificateService = $certificateService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Testing connection to ZATCA API...');

        // Check certificate
        $certificatePath = config('zatca.certificate.path');
        $privateKeyPath = $certificatePath . '/private.key';
        $certificateFilePath = $certificatePath . '/certificate.pem';

        if (!File::exists($privateKeyPath) || !File::exists($certificateFilePath)) {
            $this->error('Certificate or private key not found. Please generate them first.');
            return 1;
        }

        // Check ZATCA API configuration
        $baseUrl = config('zatca.api.base_url');
        if (empty($baseUrl)) {
            $this->error('ZATCA API URL not configured.');
            return 1;
        }

        // Test connection
        try {
            $client = new Client([
                'base_uri' => $baseUrl,
                'timeout' => 30,
                'verify' => true, // Set to false for testing if needed
            ]);

            // Get certificate data for authentication
            $certificateData = $this->certificateService->getCertificateData();

            // Create auth headers
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($certificateData['certificate_id'] . ':' . config('zatca.pih')),
            ];

            // Try a simple GET request to the status endpoint
            $response = $client->request('GET', config('zatca.api.status_url'), [
                'headers' => $headers,
                'query' => [
                    'uuid' => 'test-connection-' . time(),
                ],
            ]);

            $statusCode = $response->getStatusCode();

            // API might return 404 for a non-existent invoice, which is still a valid connection
            if ($statusCode >= 200 && $statusCode < 500) {
                $this->info('Connection to ZATCA API successful!');
                $this->info('Status code: ' . $statusCode);
                return 0;
            } else {
                $this->error('Connection failed. Status code: ' . $statusCode);
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Connection to ZATCA API failed: ' . $e->getMessage());
            return 1;
        }
    }
}