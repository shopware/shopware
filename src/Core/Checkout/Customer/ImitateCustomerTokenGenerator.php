<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token\RegisteredClaims;
use Shopware\Core\Checkout\Customer\Struct\ImitateCustomerToken;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\JWT\SalesChannel\JWTGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * @extends JWTGenerator<ImitateCustomerToken>
 */
#[Package('checkout')]
class ImitateCustomerTokenGenerator extends JWTGenerator
{
    public const HMAC_HASH_ALGORITHM = 'sha256';
    public const OPENSSL_CIPHER_ALGORITHM = 'aes-256-cbc';
    public const TOKEN_LIFETIME = 3600;

    /**
     * @internal
     */
    public function __construct(
        private readonly string $appSecret,
        private readonly Configuration $configuration,
        private readonly DataValidator $validator,
    ) {
        parent::__construct($this->configuration, $this->validator);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, use `encode` method instead
     */
    public function generate(string $salesChannelId, string $customerId, string $userId): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(ImitateCustomerTokenGenerator::class, 'generate', 'v6.8.0.0', 'parse'));

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

    /**
     * @deprecated tag:v6.8.0 - will be removed, use `decode` method instead
     */
    public function validate(string $givenToken, string $salesChannelId, string $customerId, string $userId): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(ImitateCustomerTokenGenerator::class, 'validate', 'v6.8.0.0', 'parse'));

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

    protected function getJWTStructClass(): string
    {
        return ImitateCustomerToken::class;
    }

    protected function getStructConstraints(): DataValidationDefinition
    {
        $definition = parent::getStructConstraints();
        $definition->add(RegisteredClaims::ISSUER, new NotBlank(), new NotNull());

        return $definition;
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
