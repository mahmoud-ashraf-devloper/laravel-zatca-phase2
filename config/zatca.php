<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invoice Model and Table Configuration
    |--------------------------------------------------------------------------
    |
    | Specify the fully qualified class name of your Invoice model and the
    | corresponding database table name. These will be used by the package
    | for ZATCA integration.
    |
    */
    'invoice_model' => App\Models\Invoice::class,
    'invoice_table' => 'invoices', // Default table name that can be overridden

    'credit_note_model' => App\Models\CreditNote::class, // Can be the same as invoice model
    'credit_note_table' => 'invoices', // Can be the same as invoice table

    /*
    |--------------------------------------------------------------------------
    | Environment Configuration
    |--------------------------------------------------------------------------
    |
    | Configure different environments for ZATCA integration (sandbox/production)
    |
    */
    'environment' => env('ZATCA_ENVIRONMENT', 'sandbox'), // Options: sandbox, production

    'environments' => [
        'sandbox' => [
            'base_url' => env('ZATCA_SANDBOX_URL', 'https://gw-apic-gov.gazt.gov.sa/e-invoicing/developer-portal/sandbox'),
            'compliance_url' => '/compliance',
            'reporting_url' => '/invoices/reporting/single',
            'clearance_url' => '/invoices/clearance/single',
            'status_url' => '/invoices/status',
        ],
        'production' => [
            'base_url' => env('ZATCA_API_URL', 'https://gw-apic-gov.gazt.gov.sa/e-invoicing/developer-portal'),
            'compliance_url' => '/compliance',
            'reporting_url' => '/invoices/reporting/single',
            'clearance_url' => '/invoices/clearance/single',
            'status_url' => '/invoices/status',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy API Configuration (for backward compatibility)
    |--------------------------------------------------------------------------
    */
    'api' => [
        'base_url' => env('ZATCA_API_URL', 'https://gw-apic-gov.gazt.gov.sa/e-invoicing/developer-portal'),
        'compliance_url' => '/compliance',
        'reporting_url' => '/invoices/reporting/single',
        'clearance_url' => '/invoices/clearance/single',
        'status_url' => '/invoices/status',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox Credentials
    |--------------------------------------------------------------------------
    |
    | Sandbox credentials for testing purposes
    |
    */
    'sandbox' => [
        'certificate' => env('ZATCA_SANDBOX_CERTIFICATE'),
        'private_key' => env('ZATCA_SANDBOX_PRIVATE_KEY'),
        'certificate_id' => env('ZATCA_SANDBOX_CERTIFICATE_ID'),
        'pih' => env('ZATCA_SANDBOX_PIH', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificate Storage
    |--------------------------------------------------------------------------
    */
    'certificate' => [
        'path' => storage_path('app/certificates'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Organization Details
    |--------------------------------------------------------------------------
    */
    'organization' => [
        'name' => env('ZATCA_ORG_NAME', ''),
        'tax_number' => env('ZATCA_TAX_NUMBER', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | PIH (Production Integration Handler)
    |--------------------------------------------------------------------------
    */
    'pih' => env('ZATCA_PIH', ''),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    */
    'production' => env('ZATCA_PRODUCTION', false),

    /*
    |--------------------------------------------------------------------------
    | Logging Channel
    |--------------------------------------------------------------------------
    */
    'log_channel' => env('ZATCA_LOG_CHANNEL', 'zatca'),

    /*
    |--------------------------------------------------------------------------
    | Invoice Threshold for Clearance
    |--------------------------------------------------------------------------
    */
    'clearance_threshold' => env('ZATCA_CLEARANCE_THRESHOLD', 1000),

    /*
    |--------------------------------------------------------------------------
    | Credit Note Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for handling credit notes according to ZATCA requirements.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Field Mapping Configuration
    |--------------------------------------------------------------------------
    |
    | Map your existing invoice fields to the required ZATCA fields.
    | If your application uses different field names for invoice data,
    | specify the mappings here to avoid modifying your database structure.
    |
    | Example: 'zatca_field' => 'your_application_field'
    |
    */
    'field_mapping' => [
        // Basic invoice information
        'invoice_number' => 'number',
        'invoice_type' => 'type', // 381 for credit note, 383 for debit note, 388 for standard invoice
        'issue_date' => 'created_at',
        'issue_time' => 'created_at',
        'invoice_currency_code' => 'currency_code', // SAR, USD, etc.
        'invoice_counter_value' => 'id', // Unique sequential number
        'previous_invoice_hash' => null, // For linked documents
        'payment_means_type_code' => 'payment_method',

        // Seller information
        'seller_name' => null, // Uses organization name by default if null
        'seller_tax_number' => null, // Uses organization tax number by default if null
        'seller_address' => 'seller_address',
        'seller_street' => 'seller_street',
        'seller_building_number' => 'seller_building_number',
        'seller_postal_code' => 'seller_postal_code',
        'seller_city' => 'seller_city',
        'seller_district' => 'seller_district',
        'seller_additional_number' => 'seller_additional_number',
        'seller_country_code' => 'seller_country_code', // SA for Saudi Arabia

        // Buyer information
        'buyer_name' => 'customer.name',
        'buyer_tax_number' => 'customer.tax_number',
        'buyer_address' => 'customer.address',
        'buyer_street' => 'customer.street',
        'buyer_building_number' => 'customer.building_number',
        'buyer_postal_code' => 'customer.postal_code',
        'buyer_city' => 'customer.city',
        'buyer_district' => 'customer.district',
        'buyer_additional_number' => 'customer.additional_number',
        'buyer_country_code' => 'customer.country_code',

        // Line items path - specify how to access invoice line items
        'line_items' => 'items', // e.g., Invoice->items relationship

        // Line item mappings - applied to each item in the line_items array/relationship
        'item_name' => 'name',
        'item_quantity' => 'quantity',
        'item_unit_code' => 'unit', // EA for each, KGM for kilograms, etc.
        'item_price' => 'unit_price',
        'item_price_inclusive' => 'price_inclusive_vat',
        'item_discount' => 'discount_amount',
        'item_discount_reason' => 'discount_reason',
        'item_tax_category' => 'vat_category', // S for standard rate, Z for zero rate, etc.
        'item_tax_rate' => 'vat_rate', // 15 for 15% VAT
        'item_tax_amount' => 'vat_amount',

        // Summary information
        'total_excluding_vat' => 'sub_total',
        'total_including_vat' => 'total',
        'total_vat' => 'vat_amount',
        'total_discount' => 'discount_amount',

        // Additional ZATCA fields for specific use cases
        'supply_date' => null, // If different from invoice date
        'supply_end_date' => null, // For continuous supplies
        'special_tax_treatment' => null, // For special cases like exports
        'invoice_note' => 'notes',

        // Custom fields path for additional data
        'custom_fields' => 'custom_data', // JSON/array field for additional data
    ],
];