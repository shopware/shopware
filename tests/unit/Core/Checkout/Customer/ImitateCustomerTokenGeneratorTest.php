<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Exception\InvalidImitateCustomerTokenException;
use Shopware\Core\Checkout\Customer\ImitateCustomerTokenGenerator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ImitateCustomerTokenGenerator::class)]
class ImitateCustomerTokenGeneratorTest extends TestCase
{
    private ImitateCustomerTokenGenerator $imitateCustomerTokenGenerator;

    private const SALES_CHANNEL_ID = '0146543d6a6241718da05d5ee6f6891a';
    private const CUSTOMER_ID = 'bcf76884cb764eb2b9650bb2fcf1073e';
    private const USER_ID = 'bcf76884cb764eb2b9650bb2fcf1073f';
    private const APP_SECRET = 'testAppSecret';

    protected function setUp(): void
    {
        $this->imitateCustomerTokenGenerator = new ImitateCustomerTokenGenerator(self::APP_SECRET);
    }

    #[DoesNotPerformAssertions]
    public function testValidate(): void
    {
        $token = $this->imitateCustomerTokenGenerator->generate(self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);

        $this->imitateCustomerTokenGenerator->validate($token, self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);
    }

    public function testValidateWithInvalidToken(): void
    {
        $this->expectException(InvalidImitateCustomerTokenException::class);

        $this->imitateCustomerTokenGenerator->validate('invalidToken', self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);
    }

    public function testValidateWithInvalidTimeToken(): void
    {
        $this->expectException(InvalidImitateCustomerTokenException::class);

        $token = $this->generate(self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID, time() - ImitateCustomerTokenGenerator::TOKEN_LIFETIME - 1);

        $this->imitateCustomerTokenGenerator->validate($token, self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);
    }

    private function generate(string $salesChannelId, string $customerId, string $userId, int $time): string
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

        return $this->encrypt(hash_hmac(ImitateCustomerTokenGenerator::HMAC_HASH_ALGORITHM, $data, self::APP_SECRET) . '.' . $time);
    }

    private function encrypt(string $token): string
    {
        $iv = openssl_random_pseudo_bytes((int) openssl_cipher_iv_length(ImitateCustomerTokenGenerator::OPENSSL_CIPHER_ALGORITHM));
        $encrypted = openssl_encrypt($token, ImitateCustomerTokenGenerator::OPENSSL_CIPHER_ALGORITHM, self::APP_SECRET, 0, $iv);

        if ($encrypted === false) {
            throw CustomerException::invalidImitationToken($token);
        }

        return base64_encode($iv . $encrypted);
    }
}
