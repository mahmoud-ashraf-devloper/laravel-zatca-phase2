<?php

namespace KhaledHajSalem\ZatcaPhase2\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException;
use KhaledHajSalem\ZatcaPhase2\Services\CertificateService;

class SaveZatcaCertificateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zatca:save-certificate 
                            {certificate : Path to the certificate file or certificate content}
                            {--type=compliance : Certificate type (compliance or production)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Save a ZATCA certificate received after CSR submission';

    /**
     * Execute the console command.
     *
     * @param \KhaledHajSalem\ZatcaPhase2\Services\CertificateService $certificateService
     * @return int
     */
    public function handle(CertificateService $certificateService)
    {
        $certificatePath = $this->argument('certificate');
        $type = $this->option('type');

        if (!in_array($type, ['compliance', 'production'])) {
            $this->error('Invalid certificate type. Must be "compliance" or "production".');
            return 1;
        }

        $this->info('Saving ZATCA ' . $type . ' certificate...');

        try {
            // Check if input is a path or a certificate content
            if (File::exists($certificatePath)) {
                $certificateContent = File::get($certificatePath);
                $this->info('Certificate file found at: ' . $certificatePath);
            } else {
                $certificateContent = $certificatePath;
                $this->info('Using provided certificate content.');
            }

            // Verify certificate before saving
            $verification = $certificateService->verifyCertificate($certificateContent);

            if (!$verification['valid']) {
                $this->error('Invalid certificate: ' . ($verification['error'] ?? 'Unknown error'));
                return 1;
            }

            $this->info('Certificate verified successfully.');
            $this->info('- Subject: ' . json_encode($verification['subject']));
            $this->info('- Valid until: ' . $verification['valid_to']);
            $this->info('- Certificate ID: ' . $verification['certificate_id']);

            // Confirm with user
            if (!$this->confirm('Do you want to save this certificate?', true)) {
                $this->info('Certificate saving cancelled.');
                return 0;
            }

            // Save the certificate
            $certificateService->saveCertificate($certificateContent, $type);

            $this->info('Certificate saved successfully!');
            return 0;
        } catch (ZatcaException $e) {
            $this->error('Failed to save certificate: ' . $e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->error('Unexpected error: ' . $e->getMessage());
            return 1;
        }
    }
}