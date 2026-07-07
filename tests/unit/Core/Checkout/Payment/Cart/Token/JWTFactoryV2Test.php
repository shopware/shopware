<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\Token;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\Checkout\Payment\Cart\Token\TestKey;
use Shopware\Core\Test\Stub\Checkout\Payment\Cart\Token\TestSigner;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(JWTFactoryV2::class)]
#[Package('checkout')]
#[DisabledFeatures(['v6.8.0.0'])]
class JWTFactoryV2Test extends TestCase
{
    private JWTFactoryV2 $tokenFactory;

    private Connection&Stub $connection;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->tokenFactory = $this->buildTokenFactory($this->connection);
    }

    #[DataProvider('dataProviderExpiration')]
    #[IgnoreDeprecations]
    public function testGenerateAndGetToken(int $expiration, bool $expired): void
    {
        $this->expectUserDeprecationMessage('Class "Shopware\\Core\\Checkout\\Payment\\Cart\\Token\\JWTFactoryV2" is deprecated and will be removed in v6.8.0.0. Use "Shopware\\Core\\Checkout\\Payment\\Cart\\Token\\PaymentTokenGenerator" instead.');

        if ($expired) {
            $connection = static::createStub(Connection::class);
        } else {
            $connection = $this->createMock(Connection::class);
            $connection
                ->expects($this->once())
                ->method('fetchOne')
                ->willReturn([1]);
        }
        $tokenFactory = $this->buildTokenFactory($connection);

        $transaction = self::createTransaction();
        $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), null, $expiration);
        $time = time();
        $token = $tokenFactory->generateToken($tokenStruct);
        static::assertNotEmpty($token);

        if ($expired) {
            $this->expectException(PaymentException::class);
        }

        $tokenStruct = $tokenFactory->parseToken($token);

        static::assertSame($transaction->getId(), $tokenStruct->getTransactionId());
        static::assertSame($transaction->getPaymentMethodId(), $tokenStruct->getPaymentMethodId());
        static::assertSame($token, $tokenStruct->getToken());
        static::assertEqualsWithDelta($time + $expiration, $tokenStruct->getExpires(), 1);
        static::assertSame($expired, $tokenStruct->isExpired());
    }

    #[IgnoreDeprecations]
    public function testGetInvalidFormattedToken(): void
    {
        $this->expectUserDeprecationMessage('Class "Shopware\\Core\\Checkout\\Payment\\Cart\\Token\\JWTFactoryV2" is deprecated and will be removed in v6.8.0.0. Use "Shopware\\Core\\Checkout\\Payment\\Cart\\Token\\PaymentTokenGenerator" instead.');

        $token = Uuid::randomHex();

        $this->expectExceptionObject(PaymentException::invalidToken($token));

        static::assertNotEmpty($token);

        $this->tokenFactory->parseToken($token);
    }

    #[IgnoreDeprecations]
    public function testGetTokenWithInvalidSignature(): void
    {
        $this->expectUserDeprecationMessage('Class "Shopware\\Core\\Checkout\\Payment\\Cart\\Token\\JWTFactoryV2" is deprecated and will be removed in v6.8.0.0. Use "Shopware\\Core\\Checkout\\Payment\\Cart\\Token\\PaymentTokenGenerator" instead.');

        $transaction = self::createTransaction();
        $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId());
        $token = $this->tokenFactory->generateToken($tokenStruct);
        $invalidToken = substr($token, 0, -5);

        $this->expectExceptionObject(PaymentException::invalidToken($invalidToken));

        static::assertNotEmpty($invalidToken);

        $this->tokenFactory->parseToken($invalidToken);
    }

    #[IgnoreDeprecations]
    public function testInvalidateToken(): void
    {
        $this->expectUserDeprecationMessage('Class "Shopware\\Core\\Checkout\\Payment\\Cart\\Token\\JWTFactoryV2" is deprecated and will be removed in v6.8.0.0. Use "Shopware\\Core\\Checkout\\Payment\\Cart\\Token\\PaymentTokenGenerator" instead.');

        $token = Uuid::randomHex();
        static::assertNotEmpty($token);
        $success = $this->tokenFactory->invalidateToken($token);
        static::assertFalse($success);
    }

    #[IgnoreDeprecations]
    public function testExpiredToken(): void
    {
        $this->expectUserDeprecationMessage('Class "Shopware\\Core\\Checkout\\Payment\\Cart\\Token\\JWTFactoryV2" is deprecated and will be removed in v6.8.0.0. Use "Shopware\\Core\\Checkout\\Payment\\Cart\\Token\\PaymentTokenGenerator" instead.');

        $configuration = Configuration::forSymmetricSigner(new TestSigner(), new TestKey());
        $configuration = $configuration->withValidationConstraints(new StrictValidAt(new MockClock(new \DateTimeImmutable('now - 1 day'))));
        $tokenFactory = new JWTFactoryV2($configuration, static::createStub(Connection::class), new NativeClock());

        $transaction = self::createTransaction();
        $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), null, -50);
        $token = $tokenFactory->generateToken($tokenStruct);

        $this->expectExceptionObject(PaymentException::invalidToken($token));

        static::assertNotEmpty($token);

        $tokenFactory->parseToken($token);
    }

    #[IgnoreDeprecations]
    public function testTokenNotStored(): void
    {
        $this->expectUserDeprecationMessage('Class "Shopware\\Core\\Checkout\\Payment\\Cart\\Token\\JWTFactoryV2" is deprecated and will be removed in v6.8.0.0. Use "Shopware\\Core\\Checkout\\Payment\\Cart\\Token\\PaymentTokenGenerator" instead.');

        $configuration = Configuration::forSymmetricSigner(new TestSigner(), new TestKey());
        $configuration = $configuration->withValidationConstraints(new NoopConstraint());
        $this->connection
            ->method('fetchOne')
            ->willReturn(false);

        $tokenFactory = new JWTFactoryV2($configuration, $this->connection, new NativeClock());

        $transaction = self::createTransaction();
        $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), null, -50);
        $token = $tokenFactory->generateToken($tokenStruct);

        $this->expectExceptionObject(PaymentException::tokenInvalidated($token));

        static::assertNotEmpty($token);

        $tokenFactory->parseToken($token);
    }

    public static function createTransaction(): OrderTransactionEntity
    {
        $transactionStruct = new OrderTransactionEntity();
        $transactionStruct->setId(Uuid::randomHex());
        $transactionStruct->setOrderId(Uuid::randomHex());
        $transactionStruct->setPaymentMethodId(Uuid::randomHex());
        $transactionStruct->setStateId(Uuid::randomHex());

        return $transactionStruct;
    }

    /**
     * @return iterable<array-key, array{int, bool}>
     */
    public static function dataProviderExpiration(): iterable
    {
        yield 'positive expire' => [30, false];
        yield 'negative expire' => [-30, true];
    }

    private function buildTokenFactory(Connection $connection): JWTFactoryV2
    {
        $configuration = Configuration::forSymmetricSigner(new TestSigner(), new TestKey());
        $configuration = $configuration->withValidationConstraints(new NoopConstraint());

        return new JWTFactoryV2($configuration, $connection, new NativeClock());
    }
}

/**
 * @internal
 */
#[Package('checkout')]
class NoopConstraint implements Constraint
{
    public function assert(Token $token): void
    {
    }
}
