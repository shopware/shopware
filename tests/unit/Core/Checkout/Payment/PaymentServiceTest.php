<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AbstractPaymentTransactionStructFactory;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Event\FinalizePaymentOrderTransactionCriteriaEvent;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(PaymentService::class)]
class PaymentServiceTest extends TestCase
{
    #[DisabledFeatures(['v6.7.0.0'])]
    public function testFinalize(): void
    {
        $transactionId = Uuid::randomHex();
        $transaction = new OrderTransactionEntity();
        $transaction->setId($transactionId);
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $transaction->setOrder($order);
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $tokenFactory = $this->createMock(JWTFactoryV2::class);
        $tokenFactory->expects($this->once())->method('parseToken')->with('paymentToken')->willReturn(new TokenStruct('id', 'token', 'paymentMethodId', $transactionId, 'finishUrl', \PHP_INT_MAX));
        $tokenFactory->expects($this->once())->method('invalidateToken')->with('token');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with(static::isInstanceOf(FinalizePaymentOrderTransactionCriteriaEvent::class));

        $transactionRepository = $this->createMock(EntityRepository::class);
        $transactionRepository->expects($this->once())->method('search')->with(static::callback(function (Criteria $criteria) use ($transactionId) {
            static::assertEquals($transactionId, $criteria->getIds()[0]);
            static::assertSame('payment-service::load-transaction', $criteria->getTitle());
            static::assertTrue($criteria->hasAssociation('order'));
            static::assertTrue($criteria->hasAssociation('paymentMethod'));

            return true;
        }))->willReturn(new EntitySearchResult('order_transaction', 1, new OrderTransactionCollection([$transaction]), null, new Criteria(), $context->getContext()));

        $struct = new AsyncPaymentTransactionStruct($transaction, $order, '');
        $paymentStructFactory = $this->createMock(AbstractPaymentTransactionStructFactory::class);
        $paymentStructFactory->expects($this->once())->method('async')->willReturn($struct);

        $paymentHandler = $this->createMock(AsynchronousPaymentHandlerInterface::class);
        $paymentHandler->expects($this->once())->method('finalize')->with($struct, $request, $context);

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())->method('getAsyncPaymentHandler')->willReturn($paymentHandler);

        $paymentService = new PaymentService(
            $this->createMock(PaymentTransactionChainProcessor::class),
            $tokenFactory,
            $paymentHandlerRegistry,
            $transactionRepository,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(SalesChannelContextServiceInterface::class),
            $paymentStructFactory,
            $eventDispatcher
        );

        $paymentService->finalizeTransaction('paymentToken', $request, $context);
    }
}
