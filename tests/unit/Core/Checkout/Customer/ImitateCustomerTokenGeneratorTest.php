<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use Lcobucci\JWT\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Exception\InvalidImitateCustomerTokenException;
use Shopware\Core\Checkout\Customer\ImitateCustomerTokenGenerator;
use Shopware\Core\Checkout\Customer\Struct\ImitateCustomerToken;
use Shopware\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ImitateCustomerTokenGenerator::class)]
class ImitateCustomerTokenGeneratorTest extends TestCase
{
    private const SALES_CHANNEL_ID = '0146543d6a6241718da05d5ee6f6891a';
    private const CUSTOMER_ID = 'bcf76884cb764eb2b9650bb2fcf1073e';
    private const USER_ID = 'bcf76884cb764eb2b9650bb2fcf1073f';
    private const APP_SECRET = 'testAppSecret';

    private ImitateCustomerTokenGenerator $imitateCustomerTokenGenerator;

    private DataValidator&MockObject $dataValidator;

    private Configuration $jwtConfiguration;

    protected function setUp(): void
    {
        $this->dataValidator = $this->createMock(DataValidator::class);
        $this->jwtConfiguration = JWTConfigurationFactory::createJWTConfiguration();

        $this->imitateCustomerTokenGenerator = new ImitateCustomerTokenGenerator(self::APP_SECRET, $this->jwtConfiguration, $this->dataValidator);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed with tested method
     */
    #[DoesNotPerformAssertions]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidate(): void
    {
        $token = $this->imitateCustomerTokenGenerator->generate(self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);

        $this->imitateCustomerTokenGenerator->validate($token, self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed with tested method
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateWithInvalidToken(): void
    {
        $this->expectException(InvalidImitateCustomerTokenException::class);

        $this->imitateCustomerTokenGenerator->validate('invalidToken', self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed with tested method
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateWithInvalidTimeToken(): void
    {
        $this->expectException(InvalidImitateCustomerTokenException::class);

        $token = $this->generate(self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID, time() - ImitateCustomerTokenGenerator::TOKEN_LIFETIME - 1);

        $this->imitateCustomerTokenGenerator->validate($token, self::SALES_CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);
    }

    public function testEncodeDecode(): void
    {
        $tokenStruct = new ImitateCustomerToken();
        $tokenStruct->salesChannelId = self::SALES_CHANNEL_ID;
        $tokenStruct->customerId = self::CUSTOMER_ID;
        $tokenStruct->iss = self::USER_ID;
        $token = $this->imitateCustomerTokenGenerator->encode($tokenStruct);

        $decodedToken = $this->imitateCustomerTokenGenerator->decode($token);

        static::assertSame(self::SALES_CHANNEL_ID, $decodedToken->salesChannelId);
        static::assertSame(self::CUSTOMER_ID, $decodedToken->customerId);
        static::assertSame(self::USER_ID, $decodedToken->iss);
    }

    public function testConstraint(): void
    {
        $tokenStruct = new ImitateCustomerToken();
        $token = $this->imitateCustomerTokenGenerator->encode($tokenStruct);

        $this->dataValidator
            ->expects($this->once())
            ->method('validate')
            ->with(static::isArray(), static::callback(function (DataValidationDefinition $constraints): bool {
                $property = $constraints->getProperty('iss');
                static::assertEquals([new Type('string'), new NotBlank(), new NotNull()], $property);

                return true;
            }));

        $this->imitateCustomerTokenGenerator->decode($token);
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
