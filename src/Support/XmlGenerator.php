<?php

namespace KhaledHajSalem\ZatcaPhase2\Support;

use Illuminate\Support\Facades\Log;
use KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException;
use Spatie\ArrayToXml\ArrayToXml;

class XmlGenerator
{
    /**
     * Generate XML for ZATCA from invoice or credit note data.
     *
     * @param  array  $data
     * @param  bool  $isCreditNote
     * @return string
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public static function generate(array $data, $isCreditNote = false)
    {
        try {
            // Prepare the XML structure according to ZATCA UBL format
            $xmlArray = [
                'Invoice' => [
                    '_attributes' => [
                        'xmlns' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                        'xmlns:cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        'xmlns:cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        'xmlns:ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
                    ],
                    'cbc:UBLVersionID' => '2.1',
                    'cbc:ProfileID' => 'reporting:1.0',
                    'cbc:ID' => $data['invoice_number'],
                    'cbc:UUID' => $data['invoice_uuid'] ?? (string) \Ramsey\Uuid\Uuid::uuid4(),
                    'cbc:IssueDate' => $data['issue_date'],
                    'cbc:IssueTime' => $data['issue_time'],
                    'cbc:InvoiceTypeCode' => $data['invoice_type'],
                    'cbc:DocumentCurrencyCode' => $data['invoice_currency_code'],
                    'cbc:TaxCurrencyCode' => 'SAR',

                    // Supplier (Seller) Party
                    'cac:AccountingSupplierParty' => [
                        'cac:Party' => [
                            'cac:PartyIdentification' => [
                                'cbc:ID' => $data['seller_tax_number'],
                            ],
                            'cac:PartyName' => [
                                'cbc:Name' => $data['seller_name'],
                            ],
                            'cac:PostalAddress' => [
                                'cbc:StreetName' => $data['seller_street'] ?? '',
                                'cbc:BuildingNumber' => $data['seller_building_number'] ?? '',
                                'cbc:CityName' => $data['seller_city'] ?? '',
                                'cbc:PostalZone' => $data['seller_postal_code'] ?? '',
                                'cbc:CountrySubentity' => $data['seller_district'] ?? '',
                                'cac:Country' => [
                                    'cbc:IdentificationCode' => $data['seller_country_code'],
                                ],
                            ],
                            'cac:PartyTaxScheme' => [
                                'cbc:CompanyID' => $data['seller_tax_number'],
                                'cac:TaxScheme' => [
                                    'cbc:ID' => 'VAT',
                                ],
                            ],
                            'cac:PartyLegalEntity' => [
                                'cbc:RegistrationName' => $data['seller_name'],
                            ],
                        ],
                    ],

                    // Customer (Buyer) Party
                    'cac:AccountingCustomerParty' => [
                        'cac:Party' => [
                            'cac:PartyIdentification' => [
                                'cbc:ID' => $data['buyer_tax_number'] ?? '',
                            ],
                            'cac:PartyName' => [
                                'cbc:Name' => $data['buyer_name'] ?? '',
                            ],
                            'cac:PostalAddress' => [
                                'cbc:StreetName' => $data['buyer_street'] ?? '',
                                'cbc:BuildingNumber' => $data['buyer_building_number'] ?? '',
                                'cbc:CityName' => $data['buyer_city'] ?? '',
                                'cbc:PostalZone' => $data['buyer_postal_code'] ?? '',
                                'cbc:CountrySubentity' => $data['buyer_district'] ?? '',
                                'cac:Country' => [
                                    'cbc:IdentificationCode' => $data['buyer_country_code'] ?? 'SA',
                                ],
                            ],
                            'cac:PartyTaxScheme' => [
                                'cbc:CompanyID' => $data['buyer_tax_number'] ?? '',
                                'cac:TaxScheme' => [
                                    'cbc:ID' => 'VAT',
                                ],
                            ],
                            'cac:PartyLegalEntity' => [
                                'cbc:RegistrationName' => $data['buyer_name'] ?? '',
                            ],
                        ],
                    ],

                    // Line Items
                    'cac:InvoiceLine' => self::generateInvoiceLines($data['line_items'] ?? []),

                    // Tax Total
                    'cac:TaxTotal' => [
                        'cbc:TaxAmount' => [
                            '_attributes' => ['currencyID' => 'SAR'],
                            '_value' => number_format($data['total_vat'], 2, '.', ''),
                        ],
                    ],

                    // Legal Monetary Total
                    'cac:LegalMonetaryTotal' => [
                        'cbc:LineExtensionAmount' => [
                            '_attributes' => ['currencyID' => 'SAR'],
                            '_value' => number_format($data['total_excluding_vat'], 2, '.', ''),
                        ],
                        'cbc:TaxExclusiveAmount' => [
                            '_attributes' => ['currencyID' => 'SAR'],
                            '_value' => number_format($data['total_excluding_vat'], 2, '.', ''),
                        ],
                        'cbc:TaxInclusiveAmount' => [
                            '_attributes' => ['currencyID' => 'SAR'],
                            '_value' => number_format($data['total_including_vat'], 2, '.', ''),
                        ],
                        'cbc:PayableAmount' => [
                            '_attributes' => ['currencyID' => 'SAR'],
                            '_value' => number_format($data['total_including_vat'], 2, '.', ''),
                        ],
                    ],
                ],
            ];

            // For credit notes, we need to adjust the monetary totals accordingly
            if ($isCreditNote) {
                // Create adjusted values (negative for credit notes)
                $totalExcludingVat = $data['total_excluding_vat'];
                $totalIncludingVat = $data['total_including_vat'];
                $totalVat = $data['total_vat'];
                $totalDiscount = $data['total_discount'] ?? 0;

                // Ensure amounts are negative for credit notes
                if ($totalExcludingVat > 0) $totalExcludingVat = -$totalExcludingVat;
                if ($totalIncludingVat > 0) $totalIncludingVat = -$totalIncludingVat;
                if ($totalVat > 0) $totalVat = -$totalVat;
                if ($totalDiscount > 0) $totalDiscount = -$totalDiscount;

                // Update the monetary totals
                $xmlArray['Invoice']['cac:TaxTotal']['cbc:TaxAmount']['_value'] = number_format($totalVat, 2, '.', '');

                $xmlArray['Invoice']['cac:LegalMonetaryTotal'] = [
                    'cbc:LineExtensionAmount' => [
                        '_attributes' => ['currencyID' => 'SAR'],
                        '_value' => number_format($totalExcludingVat, 2, '.', ''),
                    ],
                    'cbc:TaxExclusiveAmount' => [
                        '_attributes' => ['currencyID' => 'SAR'],
                        '_value' => number_format($totalExcludingVat, 2, '.', ''),
                    ],
                    'cbc:TaxInclusiveAmount' => [
                        '_attributes' => ['currencyID' => 'SAR'],
                        '_value' => number_format($totalIncludingVat, 2, '.', ''),
                    ],
                    'cbc:PayableAmount' => [
                        '_attributes' => ['currencyID' => 'SAR'],
                        '_value' => number_format($totalIncludingVat, 2, '.', ''),
                    ],
                ];
            }

            // Add additional information if available
            if (!empty($data['invoice_note'])) {
                $xmlArray['Invoice']['cbc:Note'] = $data['invoice_note'];
            }

            // Supply dates if specified
            if (!empty($data['supply_date'])) {
                $xmlArray['Invoice']['cbc:TaxPointDate'] = $data['supply_date'];
            }

            // Add special tax treatment if specified
            if (!empty($data['special_tax_treatment'])) {
                $xmlArray['Invoice']['cbc:TaxCurrencyCode'] = $data['special_tax_treatment'];
            }

            // Add billing reference for credit notes
            if ($isCreditNote && !empty($data['billing_reference_id'])) {
                $xmlArray['Invoice']['cac:BillingReference'] = [
                    'cac:InvoiceDocumentReference' => [
                        'cbc:ID' => $data['billing_reference_id'],
                        'cbc:UUID' => $data['billing_reference_uuid'] ?? '',
                        'cbc:IssueDate' => $data['billing_reference_date'] ?? '',
                    ],
                ];

                // For credit notes, we need to indicate negative amounts
                // This is typically done with a special purpose code
                $xmlArray['Invoice']['cbc:DocumentCurrencyCode'] = [
                    '_attributes' => ['listAgencyName' => 'United Nations Economic Commission for Europe'],
                    '_value' => $data['invoice_currency_code'],
                ];
            }

            // Convert array to XML
            $arrayToXml = new ArrayToXml($xmlArray, [
                'rootElementName' => 'root',
                '_attributes' => [
                    'xmlns' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                ],
            ]);

            // Remove the root element wrapping
            $xml = $arrayToXml->toXml();
            $xml = str_replace('<?xml version="1.0"?>', '<?xml version="1.0" encoding="UTF-8"?>', $xml);
            $xml = preg_replace('/<root>|<\/root>/', '', $xml);

            return trim($xml);
        } catch (\Exception $e) {
            Log::channel(config('zatca.log_channel', 'zatca'))->error('XML generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ZatcaException('Failed to generate XML: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate invoice lines XML structure.
     *
     * @param  array  $lineItems
     * @param  bool  $isCreditNote
     * @return array
     */
    protected static function generateInvoiceLines(array $lineItems, $isCreditNote = false)
    {
        $result = [];

        foreach ($lineItems as $index => $item) {
            // For credit notes, we need to make the amounts negative if they aren't already
            $quantity = $item['quantity'];
            $price = $item['price'];
            $lineAmount = $price * $quantity;

            if ($isCreditNote && $lineAmount > 0) {
                // Make the amount negative for credit notes
                $lineAmount = -$lineAmount;
                // We'll keep the price positive but adjust the quantity to be negative
                $quantity = -$quantity;
            }

            $lineItem = [
                'cbc:ID' => $index + 1,
                'cbc:InvoicedQuantity' => $quantity,
                'cbc:LineExtensionAmount' => [
                    '_attributes' => ['currencyID' => 'SAR'],
                    '_value' => number_format($lineAmount, 2, '.', ''),
                ],
                'cac:Item' => [
                    'cbc:Name' => $item['name'],
                    'cac:ClassifiedTaxCategory' => [
                        'cbc:ID' => $item['tax_category'],
                        'cbc:Percent' => $item['tax_rate'],
                        'cac:TaxScheme' => [
                            'cbc:ID' => 'VAT',
                        ],
                    ],
                ],
                'cac:Price' => [
                    'cbc:PriceAmount' => [
                        '_attributes' => ['currencyID' => 'SAR'],
                        '_value' => number_format($price, 2, '.', ''),
                    ],
                ],
            ];

            // Add discount if specified
            if (!empty($item['discount']) && $item['discount'] > 0) {
                $discountAmount = $item['discount'];
                if ($isCreditNote && $discountAmount > 0) {
                    $discountAmount = -$discountAmount;
                }

                $lineItem['cac:AllowanceCharge'] = [
                    'cbc:ChargeIndicator' => 'false',
                    'cbc:AllowanceChargeReason' => $item['discount_reason'] ?? 'Discount',
                    'cbc:Amount' => [
                        '_attributes' => ['currencyID' => 'SAR'],
                        '_value' => number_format($discountAmount, 2, '.', ''),
                    ],
                ];
            }

            $result[] = $lineItem;
        }

        return $result;
    }
}