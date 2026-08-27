<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStructFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentRefundProcessor::class)]
class PaymentRefundProcessorTest extends TestCase
{
    public function testTheRefundErrorSurvivesAnImpossibleFailTransition(): void
    {
        $refundId = Uuid::randomHex();
        $refundError = new \RuntimeException('the refund handler failed');

        $refundHandler = static::createStub(AbstractPaymentHandler::class);
        $refundHandler->method('supports')->willReturn(true);
        $refundHandler->method('refund')->willThrowException($refundError);

        $handlerRegistry = static::createStub(PaymentHandlerRegistry::class);
        $handlerRegistry->method('getPaymentMethodHandler')->willReturn($refundHandler);

        $stateHandler = $this->createMock(OrderTransactionCaptureRefundStateHandler::class);
        $stateHandler->expects($this->once())
            ->method('fail')
            ->willThrowException(new IllegalTransitionException('completed', 'fail', ['reopen']));

        $processor = new PaymentRefundProcessor(
            $this->createConnectionReturningAnOpenRefund(),
            $stateHandler,
            $handlerRegistry,
            new PaymentTransactionStructFactory(),
        );

        $this->expectExceptionObject($refundError);

        $processor->processRefund($refundId, Context::createDefaultContext());
    }

    private function createConnectionReturningAnOpenRefund(): Connection
    {
        $result = static::createStub(Result::class);
        $result->method('fetchAssociative')->willReturn([
            'id' => Uuid::fromHexToBytes(Uuid::randomHex()),
            'technical_name' => OrderTransactionCaptureRefundStates::STATE_OPEN,
            'payment_method_id' => Uuid::fromHexToBytes(Uuid::randomHex()),
            'transaction_id' => Uuid::fromHexToBytes(Uuid::randomHex()),
        ]);

        $queryBuilder = static::createStub(QueryBuilder::class);
        foreach (['select', 'from', 'innerJoin', 'andWhere', 'setParameter'] as $method) {
            $queryBuilder->method($method)->willReturn($queryBuilder);
        }
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return $connection;
    }
}
