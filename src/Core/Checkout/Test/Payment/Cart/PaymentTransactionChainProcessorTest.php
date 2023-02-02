<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Cart;

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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentTransactionChainProcessorTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testThrowsExceptionOnNullOrder(): void
    {
        $orderRepository = $this->createMock(EntityRepository::class);
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
            $this->createMock(SystemConfigService::class),
            $this->createMock(InitialStateIdLoader::class)
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
        $paymentMethodEntity->setId($this->ids->get('payment'));

        $transaction = new OrderTransactionEntity();
        $transaction->setId(Uuid::randomHex());
        $transaction->setStateId($this->ids->get('order-state'));
        $transaction->setPaymentMethod($paymentMethodEntity);

        $order = new OrderEntity();
        $order->setUniqueIdentifier($this->ids->get('test-order'));
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        $orderRepository = $this->createMock(EntityRepository::class);
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
            ->method('getPaymentMethodHandler')
            ->with($this->ids->get('payment'))
            ->willReturn(null);

        $initialStateIdLoader = $this->createMock(InitialStateIdLoader::class);
        $initialStateIdLoader
            ->method('get')
            ->willReturn($this->ids->get('order-state'));

        $processor = new PaymentTransactionChainProcessor(
            $this->createMock(TokenFactoryInterfaceV2::class),
            $orderRepository,
            $this->createMock(RouterInterface::class),
            $paymentHandlerRegistry,
            $this->createMock(SystemConfigService::class),
            $initialStateIdLoader
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
