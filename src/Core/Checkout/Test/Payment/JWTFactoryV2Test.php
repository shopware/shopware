<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class JWTFactoryV2Test extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var JWTFactoryV2
     */
    private $tokenFactory;

    protected function setUp(): void
    {
        $this->tokenFactory = $this->getContainer()->get(JWTFactoryV2::class);
    }

    /**
     * @dataProvider dataProviderExpiration
     */
    public function testGenerateAndGetToken(int $expiration, bool $expired): void
    {
        $transaction = self::createTransaction();
        $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), null, $expiration);
        $token = $this->tokenFactory->generateToken($tokenStruct);
        $tokenStruct = $this->tokenFactory->parseToken($token);

        static::assertEquals($transaction->getId(), $tokenStruct->getTransactionId());
        static::assertEquals($transaction->getPaymentMethodId(), $tokenStruct->getPaymentMethodId());
        static::assertEquals($token, $tokenStruct->getToken());
        static::assertEqualsWithDelta(time() + $expiration, $tokenStruct->getExpires(), 1);
        static::assertSame($expired, $tokenStruct->isExpired());
    }

    /**
     * @throws InvalidTokenException
     */
    public function testGetInvalidFormattedToken(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->tokenFactory->parseToken(Uuid::randomHex());
    }

    /**
     * @throws InvalidTokenException
     *
     * NEXT-21735 - Sometimes produces invalid base64 and returns early (but same exception)
     * @group not-deterministic
     */
    public function testGetTokenWithInvalidSignature(): void
    {
        $transaction = self::createTransaction();
        $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId());
        $token = $this->tokenFactory->generateToken($tokenStruct);
        $invalidToken = mb_substr($token, 0, -3);

        $this->expectException(InvalidTokenException::class);
        $this->tokenFactory->parseToken($invalidToken);
    }

    public function testInvalidateToken(): void
    {
        $success = $this->tokenFactory->invalidateToken(Uuid::randomHex());
        static::assertFalse($success);
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

    public function dataProviderExpiration(): array
    {
        return [
            [30, false],
            [-30, true],
        ];
    }
}
