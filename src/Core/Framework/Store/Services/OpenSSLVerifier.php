<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

class OpenSSLVerifier
{
    /**
     * @var string
     */
    private $publicKeyPath;

    /**
     * @var resource
     */
    private $keyResource;

    public function __construct(string $publicKey)
    {
        if (!is_readable($publicKey)) {
            throw new \InvalidArgumentException(sprintf('Public keyfile "%s" not readable', $publicKey));
        }

        $this->publicKeyPath = $publicKey;
    }

    public function isSystemSupported(): bool
    {
        return function_exists('openssl_verify');
    }

    public function isValid($message, $signature): bool
    {
        $pubkeyid = $this->getKeyResource();

        $signature = base64_decode($signature);

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
        throw new \RuntimeException(sprintf("Error during private key read: \n%s", implode("\n", $errors)));
    }

    private function getKeyResource()
    {
        if ($this->keyResource !== null) {
            return $this->keyResource;
        }

        $publicKey = trim(file_get_contents($this->publicKeyPath));

        if (false === $this->keyResource = openssl_pkey_get_public($publicKey)) {
            while ($errors[] = openssl_error_string()) {
            }
            throw new \RuntimeException(sprintf("Error during public key read: \n%s", implode("\n", $errors)));
        }

        return $this->keyResource;
    }
}
