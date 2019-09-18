<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactory;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class JWTFactoryTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var JWTFactory
     */
    private $tokenFactory;

    protected function setUp(): void
    {
        $this->tokenFactory = $this->getContainer()->get(JWTFactory::class);
    }

    /**
     * @throws InvalidTokenException
     */
    public function testGenerateAndGetToken(): void
    {
        $transaction = self::createTransaction();
        $token = $this->tokenFactory->generateToken($transaction);
        $tokenStruct = $this->tokenFactory->parseToken($token);

        static::assertEquals($transaction->getId(), $tokenStruct->getTransactionId());
        static::assertEquals($transaction->getPaymentMethodId(), $tokenStruct->getPaymentMethodId());
        static::assertEquals($token, $tokenStruct->getToken());
        static::assertGreaterThan(time(), $tokenStruct->getExpires());
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
     */
    public function testGetTokenWithInvalidSignature(): void
    {
        $transaction = self::createTransaction();
        $token = $this->tokenFactory->generateToken($transaction);
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
}
