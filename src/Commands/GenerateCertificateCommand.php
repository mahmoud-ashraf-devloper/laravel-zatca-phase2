<?php

namespace KhaledHajSalem\ZatcaPhase2\Commands;

use Illuminate\Console\Command;
use KhaledHajSalem\ZatcaPhase2\Services\CertificateService;

class GenerateCertificateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zatca:generate-certificate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate ZATCA compliance certificate';

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
        $this->info('Generating ZATCA certificate...');

        // Check organization details
        $orgName = config('zatca.organization.name');
        $taxNumber = config('zatca.organization.tax_number');

        if (empty($orgName) || empty($taxNumber)) {
            $this->warn('Organization details are not set in config. Please set them first.');

            // Prompt for organization details
            $orgName = $this->ask('Enter organization name', $orgName);
            $taxNumber = $this->ask('Enter tax number', $taxNumber);

            if (empty($orgName) || empty($taxNumber)) {
                $this->error('Organization name and tax number are required.');
                return 1;
            }
        }

        // Generate certificate
        try {
            $data = [
                'organization_name' => $orgName,
                'tax_number' => $taxNumber,
            ];

            $result = $this->certificateService->generateCertificateRequest($data);

            $this->info('Certificate request (CSR) generated successfully!');
            $this->info('');
            $this->info('Next steps:');
            $this->info('1. Submit the CSR to ZATCA portal');
            $this->info('2. Obtain the signed certificate from ZATCA');
            $this->info('3. Save the certificate as certificate.pem in ' . config('zatca.certificate.path'));

            // Ask if they want to view the CSR
            if ($this->confirm('Would you like to view the CSR?', false)) {
                $this->line($result['csr']);
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to generate certificate: ' . $e->getMessage());
            return 1;
        }
    }
}