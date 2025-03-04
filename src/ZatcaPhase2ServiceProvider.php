<?php

namespace KhaledHajSalem\ZatcaPhase2;

use Illuminate\Support\ServiceProvider;
use KhaledHajSalem\ZatcaPhase2\Commands\CheckZatcaStatusCommand;
use KhaledHajSalem\ZatcaPhase2\Commands\GenerateCertificateCommand;
use KhaledHajSalem\ZatcaPhase2\Commands\InstallZatcaCommand;
use KhaledHajSalem\ZatcaPhase2\Commands\TestZatcaConnectionCommand;
use KhaledHajSalem\ZatcaPhase2\Commands\TestZatcaSandboxCommand;
use KhaledHajSalem\ZatcaPhase2\Services\CertificateService;
use KhaledHajSalem\ZatcaPhase2\Services\DocumentPdfService;
use KhaledHajSalem\ZatcaPhase2\Services\InvoiceService;
use KhaledHajSalem\ZatcaPhase2\Services\ZatcaService;

class ZatcaPhase2ServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration file
        $this->publishes([
            __DIR__ . '/../config/zatca.php' => config_path('zatca.php'),
        ], 'zatca-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'zatca-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/zatca.php');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'zatca');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallZatcaCommand::class,
                GenerateCertificateCommand::class,
                TestZatcaConnectionCommand::class,
                CheckZatcaStatusCommand::class,
                TestZatcaSandboxCommand::class
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/zatca.php', 'zatca'
        );

        // Register services
        $this->app->singleton('zatca.certificate', function ($app) {
            return new CertificateService();
        });

        $this->app->singleton('zatca.invoice', function ($app) {
            return new InvoiceService();
        });

        $this->app->singleton('zatca.pdf', function ($app) {
            return new DocumentPdfService();
        });

        $this->app->singleton('zatca', function ($app) {
            return new ZatcaService(
                $app->make('zatca.certificate'),
                $app->make('zatca.invoice')
            );
        });
    }
}