<?php

namespace KhaledHajSalem\ZatcaPhase2\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallZatcaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zatca:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install ZATCA Phase 2 package resources';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Installing ZATCA Phase 2 package...');

        // Publish configuration
        $this->info('Publishing configuration...');
        $this->call('vendor:publish', [
            '--provider' => 'KhaledHajSalem\\ZatcaPhase2\\ZatcaPhase2ServiceProvider',
            '--tag' => 'zatca-config',
        ]);

        // Publish migrations
        $this->info('Publishing migrations...');
        $this->call('vendor:publish', [
            '--provider' => 'KhaledHajSalem\\ZatcaPhase2\\ZatcaPhase2ServiceProvider',
            '--tag' => 'zatca-migrations',
        ]);

        // Create logs directory
        $this->info('Setting up logging...');
        $logsPath = storage_path('logs/zatca');

        if (!File::isDirectory($logsPath)) {
            File::makeDirectory($logsPath, 0755, true);
        }

        // Create certificates directory
        $this->info('Setting up certificates directory...');
        $certificatesPath = config('zatca.certificate.path', storage_path('app/certificates'));

        if (!File::isDirectory($certificatesPath)) {
            File::makeDirectory($certificatesPath, 0755, true);
        }

        // Add logging channel configuration to config/logging.php
        $this->info('Checking logging configuration...');
        $this->setupLoggingChannel();

        $this->info('ZATCA Phase 2 package installed successfully!');
        $this->info('');
        $this->info('Next steps:');
        $this->info('1. Run migrations: php artisan migrate');
        $this->info('2. Update your .env file with ZATCA settings');
        $this->info('3. Generate a certificate: php artisan zatca:generate-certificate');

        return 0;
    }

    /**
     * Set up the logging channel.
     *
     * @return void
     */
    protected function setupLoggingChannel()
    {
        $loggingPath = config_path('logging.php');

        if (File::exists($loggingPath)) {
            $loggingContent = File::get($loggingPath);

            if (!str_contains($loggingContent, "'zatca'")) {
                $this->info('Adding ZATCA logging channel to config/logging.php');

                // This is a simple find and replace approach, which may not work in all cases
                // A more robust approach would be to parse the PHP file, but that's beyond the scope of this example
                $channelsSection = "'channels' => [";
                $zatcaChannel = "
        'zatca' => [
            'driver' => 'daily',
            'path' => storage_path('logs/zatca/zatca.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],";

                $newLoggingContent = str_replace(
                    $channelsSection,
                    $channelsSection . $zatcaChannel,
                    $loggingContent
                );

                File::put($loggingPath, $newLoggingContent);
            } else {
                $this->info('ZATCA logging channel already exists in config/logging.php');
            }
        } else {
            $this->warn('Could not find config/logging.php file');
        }
    }
}