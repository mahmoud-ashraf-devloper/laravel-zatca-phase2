<?php

namespace KhaledHajSalem\ZatcaPhase2\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException;

class CertificateService
{
    /**
     * The path to store certificates.
     *
     * @var string
     */
    protected $certificatePath;

    /**
     * Create a new certificate service instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->certificatePath = config('zatca.certificate.path');

        // Ensure certificate directory exists
        if (!File::isDirectory($this->certificatePath)) {
            File::makeDirectory($this->certificatePath, 0755, true);
        }
    }

    /**
     * Generate a new certificate request.
     *
     * @param  array  $data
     * @return array
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public function generateCertificateRequest($data)
    {
        try {
            // In a real implementation, you would generate a proper CSR
            // For demonstration purposes, we'll create a placeholder

            $orgName = $data['organization_name'] ?? config('zatca.organization.name');
            $taxNumber = $data['tax_number'] ?? config('zatca.organization.tax_number');

            if (empty($orgName) || empty($taxNumber)) {
                throw new ZatcaException('Organization name and tax number are required');
            }

            // Create private key
            $privateKey = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            // Export private key to file
            openssl_pkey_export($privateKey, $privateKeyPem);
            File::put("{$this->certificatePath}/private.key", $privateKeyPem);

            // Create CSR
            $dn = [
                "commonName" => $orgName,
                "organizationName" => $orgName,
                "organizationalUnitName" => "ZATCA Phase 2",
                "countryName" => "SA",
            ];

            $csr = openssl_csr_new($dn, $privateKey);
            openssl_csr_export($csr, $csrPem);
            File::put("{$this->certificatePath}/certificate.csr", $csrPem);

            // In a real implementation, you would submit this CSR to ZATCA
            // and store the resulting certificate

            return [
                'csr' => $csrPem,
                'private_key' => $privateKeyPem,
            ];
        } catch (\Exception $e) {
            Log::channel(config('zatca.log_channel', 'zatca'))->error('Certificate generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ZatcaException('Failed to generate certificate: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the certificate data.
     *
     * @return array
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public function getCertificateData()
    {
        // Check if we're in sandbox mode
        if (config('zatca.environment', 'sandbox') === 'sandbox') {
            return $this->getSandboxCertificateData();
        }

        $privateKeyPath = "{$this->certificatePath}/private.key";
        $certificatePath = "{$this->certificatePath}/certificate.pem";

        if (!File::exists($privateKeyPath) || !File::exists($certificatePath)) {
            throw new ZatcaException('Certificate or private key not found');
        }

        return [
            'private_key' => File::get($privateKeyPath),
            'certificate' => File::get($certificatePath),
            'certificate_id' => $this->extractCertificateId(File::get($certificatePath)),
        ];
    }

    /**
     * Get sandbox certificate data.
     *
     * @return array
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    protected function getSandboxCertificateData()
    {
        $certificate = config('zatca.sandbox.certificate');
        $privateKey = config('zatca.sandbox.private_key');
        $certificateId = config('zatca.sandbox.certificate_id');

        if (empty($certificate) || empty($privateKey) || empty($certificateId)) {
            throw new ZatcaException('Sandbox certificate data is not configured. Check your zatca.sandbox configuration.');
        }

        // If paths provided, read the files
        if (File::exists($certificate)) {
            $certificate = File::get($certificate);
        }

        if (File::exists($privateKey)) {
            $privateKey = File::get($privateKey);
        }

        return [
            'private_key' => $privateKey,
            'certificate' => $certificate,
            'certificate_id' => $certificateId,
        ];
    }

    /**
     * Sign an XML string with the certificate.
     *
     * @param  string  $xml
     * @return string
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public function signXml($xml)
    {
        try {
            // Get appropriate certificate data (sandbox or production)
            $certData = (config('zatca.environment', 'sandbox') === 'sandbox')
                ? $this->getSandboxCertificateData()
                : $this->getCertificateData();

            // In a real implementation, you would properly sign the XML
            // using the private key and certificate

            // This is a placeholder - you'd need to implement proper XML digital signatures
            // based on ZATCA requirements

            // Simulated signing
            $xmlDoc = new \DOMDocument();
            $xmlDoc->loadXML($xml);

            // Add signature element (placeholder)
            $signatureNode = $xmlDoc->createElement('Signature');
            $signatureNode->setAttribute('xmlns', 'http://www.w3.org/2000/09/xmldsig#');

            $signedInfoNode = $xmlDoc->createElement('SignedInfo');
            $signatureNode->appendChild($signedInfoNode);

            $xmlDoc->documentElement->appendChild($signatureNode);

            return $xmlDoc->saveXML();
        } catch (\Exception $e) {
            Log::channel(config('zatca.log_channel', 'zatca'))->error('XML signing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'environment' => config('zatca.environment', 'sandbox'),
            ]);

            throw new ZatcaException('Failed to sign XML: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Extract the certificate ID from a certificate.
     *
     * @param  string  $certificate
     * @return string
     */
    protected function extractCertificateId($certificate)
    {
        // In a real implementation, you would extract the actual certificate ID
        // This is a placeholder
        return md5($certificate);
    }
}