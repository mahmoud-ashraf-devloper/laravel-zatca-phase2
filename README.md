# Laravel ZATCA Phase 2 Integration

[![Latest Version on Packagist](https://img.shields.io/packagist/v/khaledhajsalem/laravel-zatca-phase2.svg?style=flat-square)](https://packagist.org/packages/khaledhajsalem/laravel-zatca-phase2)
[![Total Downloads](https://img.shields.io/packagist/dt/khaledhajsalem/laravel-zatca-phase2.svg?style=flat-square)](https://packagist.org/packages/khaledhajsalem/laravel-zatca-phase2)
[![License](https://img.shields.io/packagist/l/khaledhajsalem/laravel-zatca-phase2.svg?style=flat-square)](LICENSE)

A comprehensive Laravel package for integrating with ZATCA (Saudi Arabia Tax Authority) Phase 2 e-invoicing requirements. This package provides tools for generating, reporting, and clearing invoices and credit notes according to ZATCA Phase 2 specifications.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [Basic Configuration](#basic-configuration)
  - [Model Integration](#model-integration)
  - [Field Mapping](#field-mapping)
  - [Credit Note Configuration](#credit-note-configuration)
  - [Sandbox Mode](#sandbox-mode)
- [Usage](#usage)
  - [Invoice Reporting and Clearance](#invoice-reporting-and-clearance)
  - [Credit Note Handling](#credit-note-handling)
  - [Webhooks](#webhooks)
  - [QR Code Display](#qr-code-display)
  - [Certificate Management](#certificate-management)
- [Advanced Usage](#advanced-usage)
  - [Batch Processing](#batch-processing)
  - [Custom XML Generation](#custom-xml-generation)
  - [Event Handling](#event-handling)
- [Commands](#commands)
- [Testing](#testing)
- [Best Practices](#best-practices)
- [Common Issues and Solutions](#common-issues-and-solutions)
- [Upgrading](#upgrading)
- [Contributing](#contributing)
- [Security](#security)
- [Credits](#credits)
- [License](#license)

## Features

- **Model Flexibility:** Compatible with existing Laravel invoice and credit note models
- **Schema Adaptability:** Configurable field mapping to adapt to your existing database schema
- **Complete Document Support:** Handles both invoices and credit notes according to ZATCA requirements
- **UBL Compliance:** XML generation in ZATCA UBL format with proper validation
- **QR Code Generation:** Generates QR codes according to ZATCA requirements
- **PDF Generation:** Creates professional PDF documents for invoices and credit notes
- **Certificate Management:** Handles certificate creation and management for ZATCA compliance
- **API Integration:** Complete integration with ZATCA API for reporting and clearance
- **Queue Integration:** Process documents asynchronously using Laravel's queue system
- **Webhook Support:** Built-in webhook handling for ZATCA callbacks
- **Detailed Logging:** Comprehensive logging for audit and troubleshooting
- **Command-line Tools:** Convenient commands for installation and certificate management
- **Error Handling:** Robust error handling and reporting

## Requirements

- PHP 8.1 or higher
- Laravel 9.0 or higher
- GuzzleHTTP 7.0 or higher
- OpenSSL extension
- JSON extension
- Imagick extension

## Installation

### Composer Installation

You can install the package via composer:

```bash
composer require khaledhajsalem/laravel-zatca-phase2
```

### Package Setup

After installing the package, run the installation command:

```bash
php artisan zatca:install
```

This will:
- Publish the necessary configuration files
- Publish migrations
- Set up logging channels
- Create certificate directories

### Run Migrations

Run the migrations to add ZATCA fields to your database tables:

```bash
php artisan migrate
```

## Configuration

### Basic Configuration

After installation, configure the package in your `.env` file:

```env
# ZATCA API Configuration
# 'https://gw-fatoora.zatca.gov.sa' or 'https://gw-apic-gov.gazt.gov.sa'
ZATCA_API_URL=https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal

# ZATCA Environment Options: 'sandbox' or 'production'
ZATCA_ENVIRONMENT=sandbox

# Sandbox Configuration
ZATCA_SANDBOX_URL=https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal
ZATCA_SANDBOX_CERTIFICATE=/path/to/sandbox-certificate.pem
ZATCA_SANDBOX_PRIVATE_KEY=/path/to/sandbox-private.key
ZATCA_SANDBOX_CERTIFICATE_ID=your-sandbox-certificate-id

# Organization Details
ZATCA_ORG_NAME="Your Company Name"
ZATCA_TAX_NUMBER="Your Tax Number"

# Logging
ZATCA_LOG_CHANNEL=zatca
```

### Model Integration

The package works with your existing invoice and credit note models. Specify the models and table names in the configuration file:

```php
// config/zatca.php
'invoice_model' => App\Models\Invoice::class,
'invoice_table' => 'invoices',

'credit_note_model' => App\Models\CreditNote::class, // Can be the same as invoice model
'credit_note_table' => 'invoices', // Can be the same as invoice table
```

Add ZATCA functionality to your models with the `HasZatcaSupport` trait:

```php
use KhaledHajSalem\ZatcaPhase2\Traits\HasZatcaSupport;

class Invoice extends Model
{
    use HasZatcaSupport;
    
    // Your existing model implementation
}

class CreditNote extends Model
{
    use HasZatcaSupport;
    
    // Your existing model implementation
}
```

### Field Mapping

The package uses field mapping to adapt to your existing database schema:

```php
// config/zatca.php
'field_mapping' => [
    // Basic invoice information
    'invoice_number' => 'number',
    'invoice_type' => 'type',
    'issue_date' => 'created_at',
    
    // Seller information
    'seller_name' => 'company.name',
    'seller_tax_number' => 'company.tax_number',
    
    // Buyer information
    'buyer_name' => 'customer.name',
    'buyer_tax_number' => 'customer.tax_number',
    
    // Line items
    'line_items' => 'items',
    'item_name' => 'name',
    'item_price' => 'unit_price',
    'item_quantity' => 'quantity',
    
    // Totals
    'total_excluding_vat' => 'sub_total',
    'total_including_vat' => 'total',
    'total_vat' => 'vat_amount',
    
    // Additional fields
    'invoice_note' => 'notes',
],
```

This configuration allows the package to work with your existing database structure without requiring schema changes beyond the ZATCA-specific fields added through migrations.

### Credit Note Configuration

Credit notes require special handling for ZATCA compliance. Configure how your system handles credit notes:

```php
// config/zatca.php
'credit_note' => [
    // Determines how to identify a document as a credit note
    'identification' => [
        // Method can be 'model', 'type_field', or 'table'
        'method' => 'type_field',
        
        // If method is 'type_field', specify which field contains the type
        'type_field' => 'type',
        
        // If method is 'type_field', specify the value that indicates a credit note
        'type_value' => 'credit_note',
    ],
    
    // Determines how to link credit notes to their original invoices
    'invoice_reference' => [
        // Field in the credit note that references the original invoice
        'field' => 'original_invoice_id',
        
        // How to retrieve the original invoice number (field or relation.field)
        'number_reference' => 'originalInvoice.number',
        
        // How to retrieve the original invoice UUID (field or relation.field)
        'uuid_reference' => 'originalInvoice.zatca_invoice_uuid',
    ],
],
```

This flexibility supports different implementation approaches:

1. **Separate Models**: If your credit notes and invoices are separate models
2. **Type Field**: If you use a single model with a type field to distinguish credit notes
3. **Separate Tables**: If you store credit notes and invoices in different tables


### Sandbox Mode

The package supports ZATCA's sandbox environment for testing purposes before moving to production.

#### Configuration

Add these settings to your `.env` file:

```env
# Use sandbox mode
ZATCA_ENVIRONMENT=sandbox

# Sandbox URL (if different from default)
ZATCA_SANDBOX_URL=https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal

# Sandbox credentials
ZATCA_SANDBOX_CERTIFICATE=/path/to/sandbox-certificate.pem
ZATCA_SANDBOX_PRIVATE_KEY=/path/to/sandbox-private.key
ZATCA_SANDBOX_CERTIFICATE_ID=your-sandbox-certificate-id
```

## Usage

### Invoice Reporting and Clearance

To report and clear invoices with ZATCA:

```php
use KhaledHajSalem\ZatcaPhase2\Facades\Zatca;
use KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException;

// Report an invoice
$invoice = Invoice::find(1);
try {
    $response = Zatca::reportInvoice($invoice);
    
    // Response contains information from ZATCA
    $requestId = $response['requestID'] ?? null;
    $reportingStatus = $response['reportingStatus'] ?? null;
    
    // Handle successful reporting
    if ($reportingStatus === 'SUBMITTED') {
        // Success handling
    }
} catch (ZatcaException $e) {
    // Error handling
    $errorMessage = $e->getMessage();
    Log::error('ZATCA reporting failed', ['error' => $errorMessage, 'invoice_id' => $invoice->id]);
}

// Clear an invoice (required for invoices over the clearance threshold)
try {
    $response = Zatca::clearInvoice($invoice);
    
    // Response contains clearance information
    $clearanceStatus = $response['clearanceStatus'] ?? null;
    
    // Handle successful clearance
    if ($clearanceStatus === 'CLEARED') {
        // Clearance success handling
    }
} catch (ZatcaException $e) {
    // Clearance error handling
}

// Check invoice status
try {
    $status = Zatca::checkInvoiceStatus($invoice);
    // Process status information
} catch (ZatcaException $e) {
    // Status check error handling
}
```

### Credit Note Handling

Handling credit notes follows a similar pattern:

```php
use KhaledHajSalem\ZatcaPhase2\Facades\Zatca;

// Report a credit note
$creditNote = CreditNote::find(1);
// Or if using a type field: $creditNote = Invoice::where('type', 'credit_note')->find(1);

try {
    $response = Zatca::reportCreditNote($creditNote);
    // Handle successful reporting
} catch (ZatcaException $e) {
    // Handle exception
}

// Clear a credit note
try {
    $response = Zatca::clearCreditNote($creditNote);
    // Handle successful clearance
} catch (ZatcaException $e) {
    // Handle exception
}

// Check if a document is a credit note
if ($document->isZatcaCreditNote()) {
    // This is a credit note, handle accordingly
}

// Get the original invoice for a credit note
$originalInvoice = $creditNote->getZatcaOriginalInvoice();
```

### Webhooks

The package sets up a route for handling ZATCA callbacks. Configure this URL in your ZATCA portal settings:

```
https://your-app.com/api/zatca/callback
```

When ZATCA sends a callback to this URL, the package will update the document status automatically.

To customize the webhook handling, you can extend the `ZatcaCallbackController` class and update the route registration in your `RouteServiceProvider`.

### QR Code Display

Display the QR code on your invoices:

```php
// In your invoice view
<div class="qr-code">
    <img src="{{ $invoice->getZatcaQrCode() }}" alt="ZATCA QR Code">
</div>

// Or download QR code directly using built-in route
<a href="{{ route('zatca.documents.qr', $invoice->id) }}" target="_blank">Download QR Code</a>
```

The QR code contains the required ZATCA data in TLV (Tag-Length-Value) format, including:
- Seller name
- VAT registration number
- Timestamp
- Invoice total (with VAT)
- VAT amount
- Document hash (when available)
- Special handling for credit notes

### Certificate Management

#### ZATCA Certificate Lifecycle

The ZATCA e-invoicing system requires digital certificates for document signing. Our package streamlines this process, but some steps require interaction with ZATCA's systems:

##### 1. Certificate Signing Request (CSR) Generation

Generate a Certificate Signing Request with your organization details:

```bash
php artisan zatca:generate-certificate
```

This command:
- Creates a private key (stored securely at `storage/app/certificates/private.key`)
- Generates a CSR file at `storage/app/certificates/certificate.csr`
- Incorporates your organization details from configuration

##### 2. CSR Submission to ZATCA Portal

With the CSR generated, you must submit it to ZATCA for verification:

1. Access the [ZATCA Portal](https://fatoora.zatca.gov.sa)
2. Navigate to the E-Invoicing section
3. Select "Certificate Management"
4. Upload the CSR file (`certificate.csr`)
5. Complete the verification process as required by ZATCA
6. Note the Certificate Request ID for future reference

_Note: This step requires ZATCA account access and may involve business verification processes. Consult ZATCA documentation for current requirements._

##### 3. Certificate Retrieval

After ZATCA approves your request (typically within 1-3 business days):

1. Return to the ZATCA Portal
2. Navigate to the Certificate Management section
3. Download your approved certificate
4. Save the certificate file

##### 4. Certificate Installation

Register the approved certificate with our package:

```bash
php artisan zatca:save-certificate /path/to/downloaded-certificate.pem --type=compliance
```

For production certificates:

```bash
php artisan zatca:save-certificate /path/to/production-certificate.pem --type=production
```

This command:

- Verifies the certificate validity
- Extracts the certificate details (issue date, expiry date, certificate ID)
- Stores the certificate securely within your application
- Sets up the certificate for e-invoice signing

##### 5. Verification

Confirm your certificate is properly configured:

```bash
php artisan zatca:test-connection
```

#### Certificate Renewal

ZATCA certificates have limited validity periods. Monitor expiration dates and generate new CSRs before certificates expire to maintain compliance.

#### Sandbox Certificates

For development and testing, use sandbox mode with test certificates:

```bash
php artisan zatca:test-sandbox
```

This allows you to validate your integration without affecting production systems.


### PDF Generation

Generate professional PDF documents for invoices and credit notes:

```php
// Generate PDF with default template
$pdfContent = Zatca::generatePdf($invoice);

// With custom options
$pdfContent = Zatca::generatePdf($invoice, [
    'paper' => 'a4',
    'orientation' => 'portrait',
    'template' => 'custom.invoice.template', // Optional custom template
    'custom_css' => '.header { background-color: #f5f5f5; }',
]);

// Save to file
file_put_contents('invoice.pdf', $pdfContent);

// Or return as download response in controller
return response($pdfContent)
    ->header('Content-Type', 'application/pdf')
    ->header('Content-Disposition', 'attachment; filename="invoice-' . $invoice->number . '.pdf"');
```

The package includes default templates for both invoices and credit notes, but you can create your own custom templates:

```php
// Publish the default templates
php artisan vendor:publish --tag=zatca-views

// Then customize them in resources/views/vendor/zatca/
```

### Queue Integration

Process documents asynchronously using Laravel's queue system:

```php
// Queue a single invoice for processing
Zatca::queue($invoice, 'report');

// Queue a credit note
Zatca::queue($creditNote, 'report');

// Queue for clearance with specific queue name
Zatca::queue($invoice, 'clear', 'zatca-high');

// Queue a batch of invoices
$invoices = Invoice::needZatcaReporting()->take(100)->get();
Zatca::queueBatch($invoices, 'report', 20, function($batch) {
    // This callback is executed when the batch completes
    Mail::to('admin@example.com')->send(new ZatcaBatchCompleted($batch));
});
```

### Batch Processing

For batch processing of invoices or credit notes with queue support:

```php
use KhaledHajSalem\ZatcaPhase2\Facades\Zatca;

// Process a batch of invoices that need reporting
$invoices = Invoice::needZatcaReporting()->take(50)->get();
Zatca::queueBatch($invoices, 'report', 10);

// Process credit notes that need reporting
$creditNotes = Invoice::needZatcaCreditNoteReporting()->take(50)->get();
Zatca::queueBatch($creditNotes, 'report', 10);

// With completion callback
Zatca::queueBatch($invoices, 'report', 10, function($batch, $exception = null) {
    if ($exception) {
        // Handle batch failure
        Log::error('Batch failed', ['exception' => $exception->getMessage()]);
    } else {
        // Handle batch success
        Log::info('Batch completed', [
            'processed' => $batch->processedJobs(),
            'failed' => $batch->failedJobs,
        ]);
    }
});
```

### Custom XML Generation

To customize the XML generation process:

```php
use KhaledHajSalem\ZatcaPhase2\Support\XmlGenerator;

// Extend the XML generator
class CustomXmlGenerator extends XmlGenerator
{
    // Override methods as needed
    protected static function generateInvoiceLines(array $lineItems, $isCreditNote = false)
    {
        // Custom implementation
    }
}

// Register your custom class in a service provider
public function register()
{
    $this->app->bind(XmlGenerator::class, CustomXmlGenerator::class);
}
```

### Event Handling

The package dispatches events that you can listen for:

```php
use KhaledHajSalem\ZatcaPhase2\Events\InvoiceReported;
use KhaledHajSalem\ZatcaPhase2\Events\InvoiceCleared;
use KhaledHajSalem\ZatcaPhase2\Events\CreditNoteReported;
use KhaledHajSalem\ZatcaPhase2\Events\CreditNoteCleared;

// In your EventServiceProvider
protected $listen = [
    InvoiceReported::class => [
        // Your listeners
        SendInvoiceReportedNotification::class,
    ],
    InvoiceCleared::class => [
        // Your listeners
        UpdateAccountingSystem::class,
    ],
    CreditNoteReported::class => [
        // Your listeners
    ],
    CreditNoteCleared::class => [
        // Your listeners
    ],
];
```

## Commands

The package provides several useful Artisan commands:

```bash
# Install the package
php artisan zatca:install

# Generate a certificate
php artisan zatca:generate-certificate

# Test connection to ZATCA API
php artisan zatca:test-connection

# Check status of submitted documents
php artisan zatca:check-status

# Check only credit notes with specific status
php artisan zatca:check-status --model=credit_note --status=submitted

# Save a certificate received from ZATCA
php artisan zatca:save-certificate /path/to/certificate.pem --type=compliance

# Save a production certificate
php artisan zatca:save-certificate /path/to/production.pem --type=production
```


The status checking command helps you monitor and update the status of documents submitted to ZATCA. It can be scheduled to run automatically to keep statuses up to date.

## Testing

Run the package tests with:

```bash
composer test
```

The test suite includes:
- Unit tests for all services and components
- Feature tests for API integration
- Integration tests for database interactions

## Best Practices

### Regular Testing
Regularly test your integration using the `zatca:test-connection` command to ensure your connectivity to the ZATCA API remains active.

### Certificate Management
- Set up a process for certificate renewal before expiration
- Store certificates securely
- Implement automated alerts for certificate expiration dates

### Logging and Monitoring
- Monitor the ZATCA logs for errors and issues
- Set up alerts for failed submissions
- Implement a dashboard for tracking ZATCA compliance

### Fallback Mechanisms
- Implement retry logic for API failures
- Create queues for document submissions during API downtime
- Develop a process for manual submission when automation fails

### Data Validation
- Validate invoice data before submission to avoid rejection
- Ensure all required fields are properly mapped
- Implement pre-validation checks based on ZATCA specifications

## Common Issues and Solutions

### Certificate Issues

**Issue**: Certificate errors during API calls

**Solution**:
- Ensure the certificate is properly installed and has not expired
- Verify the private key matches the certificate
- Check certificate permissions in the storage directory

### API Connection Issues

**Issue**: Unable to connect to ZATCA API

**Solution**:
- Verify network connectivity and API URL configuration
- Check firewall and proxy settings
- Ensure SSL/TLS configurations are correct
- Use the test connection command to diagnose issues

### Data Mapping Issues

**Issue**: Invalid data in ZATCA submissions

**Solution**:
- Review field mappings in configuration
- Ensure all required fields are properly mapped
- Add data validation before submission
- Check for format issues (dates, numbers, etc.)

### Credit Note Issues

**Issue**: Credit notes rejected by ZATCA

**Solution**:
- Ensure credit notes reference valid original invoices
- Verify negative amounts are correctly formatted
- Check that billing references are properly included
- Ensure the credit note type code is correct (381)

## Upgrading

### From Version 1.x to 2.x

If you're upgrading from version 1.x:

1. Update your dependencies: `composer require khaledhajsalem/laravel-zatca-phase2:^2.0`
2. Run the upgrade command: `php artisan zatca:upgrade`
3. Check the updated configuration file for new options
4. Review the changes in field mapping structure
5. Update any custom implementations to match the new API

## Enhanced Features

I've implemented additional improvements to make the package more powerful and user-friendly:

### Improved QR Code Generation

The package now includes enhanced QR code generation that follows ZATCA's TLV (Tag-Length-Value) format requirements precisely:

```php
// Generate a QR code
$qrCode = Zatca::generateQrCode($invoice);

// Display in view
<img src="{{ $invoice->getZatcaQrCode() }}" alt="ZATCA QR Code">

// Download QR code directly
<a href="{{ route('zatca.documents.qr', $invoice->id) }}" target="_blank">Download QR Code</a>
```

The QR code includes all required ZATCA fields:
- Seller name
- VAT registration number
- Timestamp
- Invoice total (with VAT)
- VAT amount
- Document hash (if available)
- Document type (for credit notes)
- Original invoice reference (for credit notes)

### PDF Generation Support

Generate professional PDF documents for both invoices and credit notes:

```php
// Generate PDF
$pdfContent = Zatca::generatePdf($invoice, [
    'paper' => 'a4',
    'orientation' => 'portrait',
    'template' => 'custom.invoice.template', // Optional custom template
]);

// Save to file
file_put_contents('invoice.pdf', $pdfContent);

// Or download directly via route
<a href="{{ route('zatca.documents.pdf', $invoice->id) }}" target="_blank">Download PDF</a>
```

Customization options:
- Use default templates or your own custom views
- Control paper size and orientation
- Add custom CSS
- Skip item loading for large invoices

### Queue Integration

Process invoices asynchronously using Laravel's queue system:

```php
// Queue a single invoice for processing
Zatca::queue($invoice, 'report');

// Queue a credit note
Zatca::queue($creditNote, 'report');

// Queue for clearance
Zatca::queue($invoice, 'clear', 'zatca-high');

// Queue a batch of invoices
$invoices = Invoice::needZatcaReporting()->take(100)->get();
Zatca::queueBatch($invoices, 'report', 20, function($batch) {
    // This callback is executed when the batch completes
    Mail::to('admin@example.com')->send(new ZatcaBatchCompleted($batch));
});
```

Batch processing features:
- Process large numbers of invoices efficiently
- Configurable batch size
- Automatic retry logic for failed jobs
- Completion callbacks
- Queue specification

### Document Routes

The package now comes with built-in routes for document downloads:

- `/api/zatca/documents/{id}/pdf`: Download document as PDF
- `/api/zatca/documents/{id}/qr`: Download QR code as PNG
- `/api/zatca/documents/{id}/xml`: Download document XML

## Security

If you discover any security issues, please email [khaledhajsalem@hotmail.com](mailto:khaledhajsalem@hotmail.com) instead of using the issue tracker.

## Credits

- [Khaled Haj Salem](https://github.com/khaledhajsalem)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
