<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\Token;

use Lcobucci\JWT\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\Token\Constraint\PaymentTokenRegistered;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentToken;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenGenerator;
use Shopware\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentTokenGenerator::class)]
class PaymentTokenGeneratorTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
    private const PAYMENT_METHOD_ID = '0f1e2d3c4b5a69788776655443322110';

    private PaymentTokenGenerator $paymentTokenGenerator;

    private DataValidator&MockObject $dataValidator;

    private SystemConfigService&MockObject $systemConfigService;

    private Configuration $jwtConfiguration;

    protected function setUp(): void
    {
        $this->dataValidator = $this->createMock(DataValidator::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->jwtConfiguration = JWTConfigurationFactory::createJWTConfiguration();

        $this->paymentTokenGenerator = new PaymentTokenGenerator($this->jwtConfiguration, $this->dataValidator, $this->systemConfigService);
    }

    public function testEncodeDecode(): void
    {
        $tokenStruct = new PaymentToken();
        $tokenStruct->salesChannelId = self::SALES_CHANNEL_ID;
        $tokenStruct->paymentMethodId = self::PAYMENT_METHOD_ID;

        $token = $this->paymentTokenGenerator->encode($tokenStruct);

        $decoded = $this->paymentTokenGenerator->decode($token);

        static::assertSame(self::SALES_CHANNEL_ID, $decoded->salesChannelId);
        static::assertSame(self::PAYMENT_METHOD_ID, $decoded->paymentMethodId);
    }

    public function testConstraint(): void
    {
        $tokenStruct = new PaymentToken();
        $tokenStruct->salesChannelId = self::SALES_CHANNEL_ID;
        $tokenStruct->paymentMethodId = self::PAYMENT_METHOD_ID;
        $token = $this->paymentTokenGenerator->encode($tokenStruct);

        $this->dataValidator
            ->expects($this->once())
            ->method('validate')
            ->with(static::isArray(), static::callback(function (DataValidationDefinition $constraints): bool {
                $property = $constraints->getProperty('jti');
                static::assertEquals([new Type('string'), new NotBlank(), new NotNull(), new PaymentTokenRegistered()], $property);

                $sales = $constraints->getProperty('salesChannelId');
                static::assertEquals([new NotBlank(), new NotNull()], $sales);

                $payment = $constraints->getProperty('paymentMethodId');
                static::assertEquals([new NotBlank(), new NotNull()], $payment);

                return true;
            }));

        $this->paymentTokenGenerator->decode($token);
    }

    public function testGetTokenLifetimeUsesSystemConfig(): void
    {
        $this->systemConfigService
            ->expects($this->once())
            ->method('getInt')
            ->with('core.cart.paymentFinalizeTransactionTime', self::SALES_CHANNEL_ID)
            ->willReturn(10); // minutes

        $tokenStruct = new PaymentToken();
        $tokenStruct->salesChannelId = self::SALES_CHANNEL_ID;
        $tokenStruct->paymentMethodId = self::PAYMENT_METHOD_ID;

        $token = $this->paymentTokenGenerator->encode($tokenStruct);

        $decoded = $this->paymentTokenGenerator->decode($token);

        // Lifetime should be 10 * 60 = 600 seconds; decode doesn't reveal lifetime directly but getTokenLifetime used during encode to set exp.
        // We'll assert that exp claim (expiration) is approximately now + 600s
        $now = time();

        $exp = $decoded->exp?->getTimestamp();
        static::assertIsInt($exp);
        static::assertGreaterThanOrEqual($now + 590, $exp);
        static::assertLessThanOrEqual($now + 610, $exp);
    }
}
