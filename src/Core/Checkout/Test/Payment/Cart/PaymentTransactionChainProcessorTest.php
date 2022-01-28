<?php
declare(strict_types=1);

namespace Payment\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\RouterInterface;

class PaymentTransactionChainProcessorTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testThrowsExceptionOnNullOrder(): void
    {
        $orderRepository = $this->createMock(EntityRepositoryInterface::class);
        $orderRepository
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'order',
                    0,
                    new EntityCollection([]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        $processor = new PaymentTransactionChainProcessor(
            $this->createMock(TokenFactoryInterfaceV2::class),
            $orderRepository,
            $this->createMock(RouterInterface::class),
            $this->createMock(PaymentHandlerRegistry::class),
            $this->createMock(StateMachineRegistry::class),
            $this->createMock(SystemConfigService::class)
        );

        static::expectException(InvalidOrderException::class);
        static::expectExceptionMessage(
            \sprintf('The order with id %s is invalid or could not be found.', $this->ids->get('test-order'))
        );

        $processor->process(
            $this->ids->get('test-order'),
            new RequestDataBag(),
            Generator::createSalesChannelContext()
        );
    }

    public function testThrowsExceptionOnNullPaymentHandler(): void
    {
        $paymentMethodEntity = new PaymentMethodEntity();
        $paymentMethodEntity->setHandlerIdentifier($this->ids->get('handler-identifier'));

        $transaction = new OrderTransactionEntity();
        $transaction->setId(Uuid::randomHex());
        $transaction->setStateId($this->ids->get('order-state'));
        $transaction->setPaymentMethod($paymentMethodEntity);

        $order = new OrderEntity();
        $order->setUniqueIdentifier($this->ids->get('test-order'));
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        $orderRepository = $this->createMock(EntityRepositoryInterface::class);
        $orderRepository
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'order',
                    1,
                    new EntityCollection([$order]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry
            ->method('getHandlerForPaymentMethod')
            ->willReturn(null);

        $stateMachineEntity = new StateMachineStateEntity();
        $stateMachineEntity->setId($this->ids->get('order-state'));

        $stateMachineRegistry = $this->createMock(StateMachineRegistry::class);
        $stateMachineRegistry
            ->method('getInitialState')
            ->willReturn($stateMachineEntity);

        $processor = new PaymentTransactionChainProcessor(
            $this->createMock(TokenFactoryInterfaceV2::class),
            $orderRepository,
            $this->createMock(RouterInterface::class),
            $paymentHandlerRegistry,
            $stateMachineRegistry,
            $this->createMock(SystemConfigService::class)
        );

        static::expectException(UnknownPaymentMethodException::class);
        static::expectExceptionMessage(
            \sprintf('The payment method %s could not be found.', $this->ids->get('handler-identifier'))
        );

        $processor->process(
            $this->ids->get('test-order'),
            new RequestDataBag(),
            Generator::createSalesChannelContext()
        );
    }
}
