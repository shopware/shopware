<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\PaymentHandler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RecurringPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RefundPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PreparedPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\RecurringPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentHandlerRegistry::class)]
class PaymentHandlerRegistryTest extends TestCase
{
    /**
     * @var array<string, PaymentHandlerInterface>
     */
    private array $registeredHandlers = [];

    private readonly Connection $connection;

    private readonly IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();

        $qb
            ->method('setParameter')
            ->willReturnCallback(function (string $key, string $paymentMethodId): QueryBuilder {
                static::assertSame('paymentMethodId', $key);

                if (\array_key_exists($paymentMethodId, $this->registeredHandlers)) {
                    $handler = $this->registeredHandlers[$paymentMethodId];

                    $result = $this->createMock(Result::class);
                    $result
                        ->method('fetchAssociative')
                        ->willReturn(['handler_identifier' => $handler::class]);
                } else {
                    $result = $this->createMock(Result::class);
                    $result
                        ->method('fetchAssociative')
                        ->willReturn(false);
                }

                $newQb = $this->createMock(QueryBuilder::class);
                $newQb
                    ->method('executeQuery')
                    ->willReturn($result);

                return $newQb;
            });

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $this->connection = $connection;
    }

    public function testPaymentRegistry(): void
    {
        $registry = new PaymentHandlerRegistry(
            $this->registerHandler(AbstractPaymentHandler::class),
            $this->registerHandler(SynchronousPaymentHandlerInterface::class),
            $this->registerHandler(AsynchronousPaymentHandlerInterface::class),
            $this->registerHandler(PreparedPaymentHandlerInterface::class),
            $this->registerHandler(RefundPaymentHandlerInterface::class),
            $this->registerHandler(RecurringPaymentHandlerInterface::class),
            $this->connection,
        );

        $abstract = $registry->getPaymentMethodHandler($this->ids->get(AbstractPaymentHandler::class));
        static::assertInstanceOf(AbstractPaymentHandler::class, $abstract);

        $foo = $registry->getPaymentMethodHandler(Uuid::randomHex());
        static::assertNull($foo);

        if (Feature::isActive('v6.7.0.0')) {
            return;
        }

        $sync = $registry->getSyncPaymentHandler($this->ids->get(SynchronousPaymentHandlerInterface::class));
        static::assertInstanceOf(SynchronousPaymentHandlerInterface::class, $sync);

        $async = $registry->getAsyncPaymentHandler($this->ids->get(AsynchronousPaymentHandlerInterface::class));
        static::assertInstanceOf(AsynchronousPaymentHandlerInterface::class, $async);

        $prepared = $registry->getPreparedPaymentHandler($this->ids->get(PreparedPaymentHandlerInterface::class));
        static::assertInstanceOf(PreparedPaymentHandlerInterface::class, $prepared);

        $refund = $registry->getRefundPaymentHandler($this->ids->get(RefundPaymentHandlerInterface::class));
        static::assertInstanceOf(RefundPaymentHandlerInterface::class, $refund);

        $recurring = $registry->getRecurringPaymentHandler($this->ids->get(RecurringPaymentHandlerInterface::class));
        static::assertInstanceOf(RecurringPaymentHandlerInterface::class, $recurring);

        $foo = $registry->getRecurringPaymentHandler(Uuid::randomHex());
        static::assertNull($foo);
    }

    public function testPaymentRegistryWithoutServices(): void
    {
        $registry = new PaymentHandlerRegistry(
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            $this->connection,
        );

        $sync = $registry->getSyncPaymentHandler($this->ids->get(SynchronousPaymentHandlerInterface::class));
        static::assertNull($sync);

        $async = $registry->getAsyncPaymentHandler($this->ids->get(AsynchronousPaymentHandlerInterface::class));
        static::assertNull($async);

        $prepared = $registry->getPreparedPaymentHandler($this->ids->get(PreparedPaymentHandlerInterface::class));
        static::assertNull($prepared);

        $refund = $registry->getRefundPaymentHandler($this->ids->get(RefundPaymentHandlerInterface::class));
        static::assertNull($refund);

        $recurring = $registry->getRecurringPaymentHandler($this->ids->get(RecurringPaymentHandlerInterface::class));
        static::assertNull($recurring);

        $foo = $registry->getRecurringPaymentHandler(Uuid::randomHex());
        static::assertNull($foo);
    }

    public function testRegistryWithNonPaymentInterfaceService(): void
    {
        $registry = new PaymentHandlerRegistry(
            new ServiceLocator([
                AbstractPaymentHandler::class => fn () => new class() {
                },
            ]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            $this->connection,
        );

        $handler = $registry->getPaymentMethodHandler($this->ids->get(AbstractPaymentHandler::class));
        static::assertNull($handler);
    }

    public function testRegistryWithNonRegisteredPaymentHandler(): void
    {
        $this->registerHandler(SynchronousPaymentHandlerInterface::class);

        $registry = new PaymentHandlerRegistry(
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            $this->connection,
        );

        $sync = $registry->getPaymentMethodHandler($this->ids->get(SynchronousPaymentHandlerInterface::class));
        static::assertNull($sync);
    }

    public function testRegistryWithMismatchedExpectedType(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $registry = new PaymentHandlerRegistry(
            new ServiceLocator([]),
            $this->registerHandler(AsynchronousPaymentHandlerInterface::class),
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            $this->connection,
        );

        $sync = $registry->getSyncPaymentHandler($this->ids->get(AsynchronousPaymentHandlerInterface::class));
        static::assertNull($sync);
    }

    public function testConnectionQueryBuilder(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb
            ->expects(static::once())
            ->method('select')
            ->with('
                payment_method.handler_identifier,
                app_payment_method.id as app_payment_method_id
            ')
            ->willReturnSelf();

        $qb
            ->expects(static::once())
            ->method('from')
            ->with('payment_method')
            ->willReturnSelf();

        $qb
            ->expects(static::once())
            ->method('leftJoin')
            ->with(
                'payment_method',
                'app_payment_method',
                'app_payment_method',
                'payment_method.id = app_payment_method.payment_method_id'
            )
            ->willReturnSelf();

        $qb
            ->expects(static::once())
            ->method('andWhere')
            ->with('payment_method.id = :paymentMethodId')
            ->willReturnSelf();

        $uuid = Uuid::randomHex();

        $qb
            ->expects(static::once())
            ->method('setParameter')
            ->with('paymentMethodId', Uuid::fromHexToBytes($uuid))
            ->willReturnSelf();

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $registry = new PaymentHandlerRegistry(
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            new ServiceLocator([]),
            $connection,
        );

        $registry->getPaymentMethodHandler($uuid, 'foo');
    }

    /**
     * @param class-string<PaymentHandlerInterface> $handler
     *
     * @return ServiceLocator<PaymentHandlerInterface>
     */
    private function registerHandler(string $handler): ServiceLocator
    {
        $class = match ($handler) {
            AbstractPaymentHandler::class => new class() extends AbstractPaymentHandler {
                public function supports(string $paymentMethodId, Context $context): array
                {
                    return [];
                }

                public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
                {
                    return null;
                }
            },
            SynchronousPaymentHandlerInterface::class => new class() implements SynchronousPaymentHandlerInterface {
                public function pay(SyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
                {
                }
            },
            AsynchronousPaymentHandlerInterface::class => new class() implements AsynchronousPaymentHandlerInterface {
                public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
                {
                    return new RedirectResponse('https://example.com');
                }

                public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
                {
                }
            },
            PreparedPaymentHandlerInterface::class => new class() implements PreparedPaymentHandlerInterface {
                public function validate(Cart $cart, RequestDataBag $requestDataBag, SalesChannelContext $context): Struct
                {
                    return new ArrayStruct();
                }

                public function capture(PreparedPaymentTransactionStruct $transaction, RequestDataBag $requestDataBag, SalesChannelContext $context, Struct $preOrderPaymentStruct): void
                {
                }
            },
            RefundPaymentHandlerInterface::class => new class() implements RefundPaymentHandlerInterface {
                public function refund(string $refundId, Context $context): void
                {
                }
            },
            RecurringPaymentHandlerInterface::class => new class() implements RecurringPaymentHandlerInterface {
                public function captureRecurring(RecurringPaymentTransactionStruct $transaction, Context $context): void
                {
                }
            },
            default => new class() implements PaymentHandlerInterface {
            },
        };

        $this->registeredHandlers[Uuid::fromHexToBytes($this->ids->get($handler))] = $class;

        return new ServiceLocator([$class::class => fn () => $class]);
    }
}
