<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Service;

use Shopware\Core\Maintenance\System\Exception\JwtCertificateGenerationException;

class JwtCertificateGenerator
{
    public function generate(string $privateKeyPath, string $publicKeyPath, ?string $passphrase = null): void
    {
        $key = openssl_pkey_new([
            'digest_alg' => 'aes256',
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
            'encrypt_key' => (bool) $passphrase,
            'encrypt_key_cipher' => \OPENSSL_CIPHER_AES_256_CBC,
        ]);

        if ($key === false) {
            throw new JwtCertificateGenerationException('Failed to generate key');
        }

        // export private key
        $result = openssl_pkey_export_to_file($key, $privateKeyPath, $passphrase);
        if ($result === false) {
            throw new JwtCertificateGenerationException('Could not export private key to file');
        }

        chmod($privateKeyPath, 0660);

        // export public key
        $keyData = openssl_pkey_get_details($key);
        if ($keyData === false) {
            throw new JwtCertificateGenerationException('Failed to export public key');
        }

        file_put_contents($publicKeyPath, $keyData['key']);
        chmod($publicKeyPath, 0660);
    }
}
