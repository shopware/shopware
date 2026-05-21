<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Security;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * AES-256-GCM at-rest encryption for UCP private keys. The encryption key is
 * derived per-row from APP_SECRET via HKDF-SHA256, with the row's `kid` as the
 * HKDF info parameter — ensuring distinct ciphertext per key and ruling out
 * cross-row replay even if ciphertexts leak.
 *
 * Wire format (binary, base64-encoded for DB storage):
 *
 *   1 byte  version (0x01)
 *  12 bytes IV (random)
 *  16 bytes auth tag
 *  remaining ciphertext (variable)
 *
 * @internal
 */
#[Package('framework')]
class PrivateKeyEncryptor
{
    public const HKDF_SALT = 'ucp/signing-key-v1';
    private const VERSION = 1;
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const CIPHER = 'aes-256-gcm';

    public function __construct(
        private readonly ?string $appSecretOverride = null,
    ) {
    }

    public function encrypt(string $plaintext, string $kid): string
    {
        $key = $this->deriveKey($kid);
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            \OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $kid,
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw UcpException::encryptionFailed('openssl_encrypt returned false');
        }

        return \chr(self::VERSION) . $iv . $tag . $ciphertext;
    }

    public function decrypt(string $blob, string $kid): string
    {
        if (\strlen($blob) < 1 + self::IV_LENGTH + self::TAG_LENGTH + 1) {
            throw UcpException::keyDecryptionFailed();
        }

        $version = \ord($blob[0]);
        if ($version !== self::VERSION) {
            throw UcpException::keyDecryptionFailed();
        }

        $iv = substr($blob, 1, self::IV_LENGTH);
        $tag = substr($blob, 1 + self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($blob, 1 + self::IV_LENGTH + self::TAG_LENGTH);

        $key = $this->deriveKey($kid);
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            \OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $kid
        );

        if ($plaintext === false) {
            throw UcpException::keyDecryptionFailed();
        }

        return $plaintext;
    }

    /**
     * Re-encrypts a blob from one APP_SECRET to another. Used by
     * `ucp:keys:reencrypt` after a secret rotation.
     */
    public function reencrypt(string $blob, string $kid, string $oldSecret, string $newSecret): string
    {
        $plaintext = (new self($oldSecret))->decrypt($blob, $kid);

        return (new self($newSecret))->encrypt($plaintext, $kid);
    }

    private function deriveKey(string $kid): string
    {
        $secret = $this->appSecretOverride ?? (string) EnvironmentHelper::getVariable('APP_SECRET');
        if ($secret === '') {
            throw UcpException::encryptionFailed('APP_SECRET is empty');
        }

        return hash_hkdf('sha256', $secret, 32, $kid, self::HKDF_SALT);
    }
}
