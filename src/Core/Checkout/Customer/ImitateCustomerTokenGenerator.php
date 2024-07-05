<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ImitateCustomerTokenGenerator
{
    public const HMAC_HASH_ALGORITHM = 'sha256';
    public const OPENSSL_CIPHER_ALGORITHM = 'aes-256-cbc';
    public const TOKEN_LIFETIME = 3600;

    /**
     * @internal
     */
    public function __construct(
        private readonly string $appSecret
    ) {
    }

    public function generate(string $salesChannelId, string $customerId, string $userId): string
    {
        $tokenData = [
            'salesChannelId' => $salesChannelId,
            'customerId' => $customerId,
            'userId' => $userId,
        ];

        $data = json_encode($tokenData);

        if ($data === false) {
            throw CustomerException::invalidImitationToken($salesChannelId . ':' . $customerId . ':' . $userId);
        }

        return $this->encrypt(hash_hmac(self::HMAC_HASH_ALGORITHM, $data, $this->appSecret) . '.' . time());
    }

    public function validate(string $givenToken, string $salesChannelId, string $customerId, string $userId): void
    {
        $tokenData = $this->decrypt($givenToken);

        $tokenData = explode('.', $tokenData);

        if (\count($tokenData) !== 2) {
            throw CustomerException::invalidImitationToken($givenToken);
        }

        $hash = $tokenData[0];
        $timeDiff = time() - (int) $tokenData[1];

        if ($timeDiff > self::TOKEN_LIFETIME) {
            throw CustomerException::invalidImitationToken($givenToken);
        }

        $givenTokenData = [
            'salesChannelId' => $salesChannelId,
            'customerId' => $customerId,
            'userId' => $userId,
        ];

        $data = json_encode($givenTokenData);

        if ($data === false) {
            throw CustomerException::invalidImitationToken($givenToken);
        }

        $expectedHash = hash_hmac(self::HMAC_HASH_ALGORITHM, $data, $this->appSecret);

        if (!hash_equals($hash, $expectedHash)) {
            throw CustomerException::invalidImitationToken($givenToken);
        }
    }

    private function encrypt(string $token): string
    {
        $iv = openssl_random_pseudo_bytes((int) openssl_cipher_iv_length(self::OPENSSL_CIPHER_ALGORITHM));
        $encrypted = openssl_encrypt($token, self::OPENSSL_CIPHER_ALGORITHM, $this->appSecret, 0, $iv);

        if ($encrypted === false) {
            throw CustomerException::invalidImitationToken($token);
        }

        return base64_encode($iv . $encrypted);
    }

    private function decrypt(string $token): string
    {
        $data = base64_decode($token, true);

        if ($data === false) {
            throw CustomerException::invalidImitationToken($token);
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        if (\strlen($iv) !== 16) {
            throw CustomerException::invalidImitationToken($token);
        }

        $decrypted = openssl_decrypt($encrypted, self::OPENSSL_CIPHER_ALGORITHM, $this->appSecret, 0, $iv);

        if ($decrypted === false) {
            throw CustomerException::invalidImitationToken($token);
        }

        return $decrypted;
    }
}
