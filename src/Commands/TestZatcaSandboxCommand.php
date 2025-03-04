<?php

namespace KhaledHajSalem\ZatcaPhase2\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use KhaledHajSalem\ZatcaPhase2\Services\ZatcaService;

class TestZatcaSandboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zatca:test-sandbox';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test connection to ZATCA sandbox environment';

    /**
     * Execute the console command.
     *
     * @param  \KhaledHajSalem\ZatcaPhase2\Services\ZatcaService  $zatcaService
     * @return int
     */
    public function handle(ZatcaService $zatcaService)
    {
        $this->info('Testing connection to ZATCA sandbox environment...');

        if (!$zatcaService->isSandboxMode()) {
            $this->error('Not in sandbox mode! Please set ZATCA_ENVIRONMENT=sandbox in your .env file');
            return 1;
        }

        // Display current sandbox configuration
        $this->info('Current Sandbox Configuration:');
        $this->info('- Base URL: ' . config('zatca.environments.sandbox.base_url'));
        $this->info('- Certificate ID: ' . (config('zatca.sandbox.certificate_id') ? 'Set' : 'Not set'));
        $this->info('- PIH: ' . (config('zatca.sandbox.pih') ? 'Set' : 'Not set'));

        try {
            // Perform a simple request to test connectivity
            $response = $zatcaService->client->request('GET', $zatcaService->statusUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'uuid' => 'test-sandbox-connection-' . time(),
                ],
                'http_errors' => false,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            // Even a 404 is fine for testing connectivity, as long as we get a ZATCA response
            if ($statusCode >= 200 && $statusCode < 500) {
                $this->info('Connection to ZATCA sandbox successful!');
                $this->info('Status code: ' . $statusCode);
                $this->info('Response: ' . substr($body, 0, 100) . (strlen($body) > 100 ? '...' : ''));
                return 0;
            } else {
                $this->error('Connection failed with server error. Status code: ' . $statusCode);
                $this->error('Response: ' . $body);
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Connection to ZATCA sandbox failed: ' . $e->getMessage());

            // Provide troubleshooting help
            $this->line('');
            $this->line('Troubleshooting tips:');
            $this->line('1. Check your internet connection');
            $this->line('2. Verify the sandbox URL is correct');
            $this->line('3. Check if ZATCA sandbox services are currently available');
            $this->line('4. Ensure your firewall/proxy allows connections to the ZATCA API');

            return 1;
        }
    }
}