<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Exception\StoreSignatureValidationException;

/**
 * @internal
 */
#[Package('merchant-services')]
class OpenSSLVerifier
{
    private string $publicKeyPath;

    private ?\OpenSSLAsymmetricKey $keyResource = null;

    /**
     * @param list<string> $publicKeys
     */
    public function __construct(array $publicKeys)
    {
        foreach ($publicKeys as $publicKey) {
            if (is_readable($publicKey)) {
                $this->publicKeyPath = $publicKey;

                return;
            }
        }

        throw new StoreSignatureValidationException(sprintf('Cannot find readable public key file in %s', implode(',', $publicKeys)));
    }

    public function isSystemSupported(): bool
    {
        return \function_exists('openssl_verify');
    }

    public function isValid(string $message, string $signature): bool
    {
        $errors = [];
        $pubkeyid = $this->getKey();

        $signature = base64_decode($signature, true);
        if ($signature === false) {
            throw new StoreSignatureValidationException('Invalid signature');
        }

        // State whether signature is okay or not
        $ok = openssl_verify($message, $signature, $pubkeyid);

        if ($ok === 1) {
            return true;
        }
        if ($ok === 0) {
            return false;
        }
        while ($errors[] = openssl_error_string()) {
        }

        throw new StoreSignatureValidationException(sprintf("Error during private key read: \n%s", implode("\n", $errors)));
    }

    private function getKey(): \OpenSSLAsymmetricKey
    {
        $errors = [];
        if ($this->keyResource !== null) {
            return $this->keyResource;
        }

        $publicKey = trim((string) file_get_contents($this->publicKeyPath));

        $key = openssl_pkey_get_public($publicKey);
        if ($key === false) {
            while ($errors[] = openssl_error_string()) {
            }

            throw new StoreSignatureValidationException(sprintf("Error during public key read: \n%s", implode("\n", $errors)));
        }

        $this->keyResource = $key;

        return $this->keyResource;
    }
}
