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
     * Generate a new certificate request (CSR).
     *
     * @param  array  $data
     * @return array
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public function generateCertificateRequest(array $data)
    {
        try {
            $orgName = $data['organization_name'] ?? config('zatca.organization.name');
            $taxNumber = $data['tax_number'] ?? config('zatca.organization.tax_number');

            if (empty($orgName) || empty($taxNumber)) {
                throw new ZatcaException('Organization name and tax number are required');
            }

            // Create private key (2048 bits as required by ZATCA)
            $privateKey = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'digest_alg' => 'sha256',
            ]);

            if (!$privateKey) {
                throw new ZatcaException('Failed to generate private key: ' . openssl_error_string());
            }

            // Export private key to file
            openssl_pkey_export($privateKey, $privateKeyPem);
            File::put("{$this->certificatePath}/private.key", $privateKeyPem);

            // Create CSR with required ZATCA fields
            $dn = [
                "commonName" => $orgName,
                "organizationName" => $orgName,
                "organizationalUnitName" => $data['org_unit'] ?? 'IT Department',
                "countryName" => "SA",
                "localityName" => $data['city'] ?? 'Riyadh',
                "stateOrProvinceName" => $data['state'] ?? 'Riyadh',
                "emailAddress" => $data['email'] ?? 'zatca@' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $orgName)) . '.com',
                "serialNumber" => $taxNumber, // VAT registration number is required by ZATCA
            ];

            $csr = openssl_csr_new($dn, $privateKey, [
                'digest_alg' => 'sha256',
                'req_extensions' => array(
                    'keyUsage' => 'digitalSignature,nonRepudiation,keyEncipherment,dataEncipherment',
                    'extendedKeyUsage' => 'clientAuth,emailProtection',
                )
            ]);

            if (!$csr) {
                throw new ZatcaException('Failed to generate CSR: ' . openssl_error_string());
            }

            // Export CSR
            openssl_csr_export($csr, $csrPem);
            File::put("{$this->certificatePath}/certificate.csr", $csrPem);

            // Generate and save compliance request ID for future reference
            $complianceRequestId = md5($csrPem . time());
            File::put("{$this->certificatePath}/compliance_request_id.txt", $complianceRequestId);

            return [
                'csr' => $csrPem,
                'private_key' => $privateKeyPem,
                'compliance_request_id' => $complianceRequestId,
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
     * Save a received signed certificate from ZATCA.
     *
     * @param  string  $certificateContent
     * @param  string  $type  Either 'compliance' or 'production'
     * @return bool
     * @throws \KhaledHajSalem\ZatcaPhase2\Exceptions\ZatcaException
     */
    public function saveCertificate($certificateContent, $type = 'compliance')
    {
        try {
            // Validate certificate
            $cert = openssl_x509_read($certificateContent);
            if (!$cert) {
                throw new ZatcaException('Invalid certificate: ' . openssl_error_string());
            }

            // Get certificate details
            $certDetails = openssl_x509_parse($cert);

            // Extract certificate ID (serial number)
            $certificateId = $certDetails['serialNumberHex'] ?? ($certDetails['serialNumber'] ?? null);

            if (!$certificateId) {
                throw new ZatcaException('Could not extract certificate ID');
            }

            // Save certificate
            $filename = $type === 'compliance' ? 'compliance_certificate.pem' : 'certificate.pem';
            File::put("{$this->certificatePath}/{$filename}", $certificateContent);

            // Save certificate ID
            File::put("{$this->certificatePath}/{$type}_certificate_id.txt", $certificateId);

            return true;
        } catch (\Exception $e) {
            Log::channel(config('zatca.log_channel', 'zatca'))->error('Certificate saving failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'type' => $type,
            ]);

            throw new ZatcaException('Failed to save certificate: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the certificate data based on environment.
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
        $certificateIdPath = "{$this->certificatePath}/production_certificate_id.txt";

        if (!File::exists($privateKeyPath)) {
            throw new ZatcaException('Private key not found at: ' . $privateKeyPath);
        }

        if (!File::exists($certificatePath)) {
            throw new ZatcaException('Certificate not found at: ' . $certificatePath);
        }

        $certificateId = File::exists($certificateIdPath)
            ? trim(File::get($certificateIdPath))
            : $this->extractCertificateId(File::get($certificatePath));

        return [
            'private_key' => File::get($privateKeyPath),
            'certificate' => File::get($certificatePath),
            'certificate_id' => $certificateId,
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
     * Sign an XML document according to ZATCA requirements.
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

            $privateKey = $certData['private_key'];
            $certificate = $certData['certificate'];

            // Load the XML document
            $dom = new \DOMDocument();
            $dom->loadXML($xml);

            // Create a new Security Token Reference
            $objDSig = new \XMLSecurityDSig();
            $objDSig->setCanonicalMethod(\XMLSecurityDSig::EXC_C14N);

            // Create reference to be signed
            $objDSig->addReference(
                $dom,
                \XMLSecurityDSig::SHA256,
                ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
                ['force_uri' => true]
            );

            // Create a new (private) security key
            $objKey = new \XMLSecurityKey(\XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
            $objKey->loadKey($privateKey);

            // Calculate the signature
            $objDSig->sign($objKey);

            // Add the certificate to the signature
            $objDSig->add509Cert($certificate);

            // Append the signature to the XML
            $objDSig->appendSignature($dom->documentElement);

            return $dom->saveXML();
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
     * Extract the certificate ID (serial number) from a certificate.
     *
     * @param  string  $certificate
     * @return string
     */
    protected function extractCertificateId($certificate)
    {
        try {
            $cert = openssl_x509_read($certificate);
            $certDetails = openssl_x509_parse($cert);

            // Return the serial number in hex format (ZATCA requirement)
            return $certDetails['serialNumberHex'] ?? $certDetails['serialNumber'] ?? md5($certificate);
        } catch (\Exception $e) {
            // Fallback to hash in case of failure
            return md5($certificate);
        }
    }

    /**
     * Verify if a certificate is valid.
     *
     * @param  string  $certificateContent
     * @return array
     */
    public function verifyCertificate($certificateContent)
    {
        try {
            $cert = openssl_x509_read($certificateContent);
            $certInfo = openssl_x509_parse($cert);

            // Check expiration
            $validFrom = \DateTime::createFromFormat('ymdHise', $certInfo['validFrom']);
            $validTo = \DateTime::createFromFormat('ymdHise', $certInfo['validTo']);
            $now = new \DateTime();

            $isExpired = $now > $validTo;
            $isNotYetValid = $now < $validFrom;

            return [
                'valid' => !$isExpired && !$isNotYetValid,
                'expired' => $isExpired,
                'not_yet_valid' => $isNotYetValid,
                'subject' => $certInfo['subject'],
                'issuer' => $certInfo['issuer'],
                'valid_from' => $validFrom->format('Y-m-d H:i:s'),
                'valid_to' => $validTo->format('Y-m-d H:i:s'),
                'certificate_id' => $certInfo['serialNumberHex'] ?? $certInfo['serialNumber'],
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get a human-readable certificate information.
     *
     * @param  string  $type  Either 'compliance', 'production', or 'sandbox'
     * @return array
     */
    public function getCertificateInfo($type = 'production')
    {
        try {
            $certificate = null;

            if ($type === 'sandbox') {
                $certificateContent = config('zatca.sandbox.certificate');
                if (File::exists($certificateContent)) {
                    $certificate = File::get($certificateContent);
                } else {
                    $certificate = $certificateContent;
                }
            } else {
                $filename = $type === 'compliance' ? 'compliance_certificate.pem' : 'certificate.pem';
                $certificatePath = "{$this->certificatePath}/{$filename}";

                if (!File::exists($certificatePath)) {
                    return [
                        'valid' => false,
                        'error' => 'Certificate not found',
                        'path' => $certificatePath,
                    ];
                }

                $certificate = File::get($certificatePath);
            }

            return $this->verifyCertificate($certificate);
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}