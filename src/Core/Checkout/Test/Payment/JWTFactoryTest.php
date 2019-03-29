<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactory;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class JWTFactoryTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var JWTFactory
     */
    protected $tokenFactory;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var PaymentService
     */
    private $paymentService;

    protected function setUp(): void
    {
        $this->tokenFactory = $this->getContainer()->get(JWTFactory::class);
        $this->paymentService = $this->getContainer()->get(PaymentService::class);
        $this->context = Context::createDefaultContext();
    }

    /**
     * @throws InvalidTokenException
     */
    public function testGenerateAndGetToken(): void
    {
        $transaction = self::createTransaction();
        $token = $this->tokenFactory->generateToken($transaction, $this->context);
        $tokenStruct = $this->tokenFactory->parseToken($token, $this->context);

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
        $this->tokenFactory->parseToken(Uuid::randomHex(), $this->context);
    }

    /**
     * @throws InvalidTokenException
     */
    public function testGetTokenWithInvalidSignature(): void
    {
        $transaction = self::createTransaction();
        $token = $this->tokenFactory->generateToken($transaction, $this->context);
        $invalidToken = substr($token, 0, -3);

        $this->expectException(InvalidTokenException::class);
        $this->tokenFactory->parseToken($invalidToken, $this->context);
    }

    public function testInvalidateToken(): void
    {
        $success = $this->tokenFactory->invalidateToken(Uuid::randomHex(), $this->context);
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
