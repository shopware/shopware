<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Service;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\MaintenanceException;

/**
 * @deprecated tag:v6.7.0 - will be removed without a replacement
 */
#[Package('core')]
class JwtCertificateGenerator
{
    /**
     * @return array{0: string, 1: string}
     */
    public function generateString(?string $passphrase = null): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            'Using RSA JWT keys are deprecated and will be removed with Shopware 6.7.0.0. Consider enabling HMAC JWT keys using shopware.api.jwt_key.use_app_secret'
        );

        $key = \openssl_pkey_new([
            'private_key_bits' => 2048,
            'digest_alg' => 'aes256',
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
            'encrypt_key' => (bool) $passphrase,
            'encrypt_key_cipher' => \OPENSSL_CIPHER_AES_256_CBC,
        ]);

        if ($key === false) {
            throw MaintenanceException::jwtCertificateGenerationFailed('Failed to generate key');
        }

        if (!openssl_pkey_export($key, $privateKey, $passphrase)) {
            throw MaintenanceException::jwtCertificateGenerationFailed('Failed to export private key');
        }

        $keyData = openssl_pkey_get_details($key);
        if ($keyData === false) {
            throw MaintenanceException::jwtCertificateGenerationFailed('Failed to export public key');
        }

        return [$privateKey, $keyData['key']];
    }

    public function generate(string $privateKeyPath, string $publicKeyPath, ?string $passphrase = null): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            'Using RSA JWT keys are deprecated and will be removed with Shopware 6.7.0.0. Consider enabling HMAC JWT keys using shopware.api.jwt_key.use_app_secret'
        );

        [$private, $public] = $this->generateString($passphrase);

        // Ensure that the directories we should generate the public / private key exist.
        $privateKeyDirectory = \dirname($privateKeyPath);
        if (!\is_dir($privateKeyDirectory)) {
            \mkdir($privateKeyDirectory, 0755, true);
        }

        $publicKeyDirectory = \dirname($publicKeyPath);
        if (!\is_dir($publicKeyDirectory)) {
            \mkdir($publicKeyDirectory, 0755, true);
        }

        \file_put_contents($privateKeyPath, $private);
        \chmod($privateKeyPath, 0660);

        \file_put_contents($publicKeyPath, $public);
        \chmod($publicKeyPath, 0660);
    }
}
