<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

/**
 * Symmetric encryption for secrets at rest (OAuth2 client secrets, TOTP seeds).
 *
 * Uses AES-256-GCM with a key derived from the application secret and a random per-value nonce.
 * The output is `base64( nonce(12) | ciphertext | tag(16) )` and self-contained for decryption.
 *
 * @internal
 */
#[Package('framework')]
class SecretEncryptor
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private readonly string $key;

    public function __construct(string $appSecret)
    {
        // Derive a fixed-length 256-bit key from the application secret.
        $this->key = Hasher::hashBinary('admin-auth:' . $appSecret, 'sha256');
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            \OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw AdminAuthException::encryptionFailed();
        }

        return base64_encode($nonce . $ciphertext . $tag);
    }

    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);

        if ($raw === false || \strlen($raw) < self::NONCE_LENGTH + self::TAG_LENGTH) {
            throw AdminAuthException::decryptionFailed();
        }

        $nonce = substr($raw, 0, self::NONCE_LENGTH);
        $tag = substr($raw, -self::TAG_LENGTH);
        $ciphertext = substr($raw, self::NONCE_LENGTH, -self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            \OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw AdminAuthException::decryptionFailed();
        }

        return $plaintext;
    }
}
