<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\PaymentHandler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
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
     * @var array<string, AbstractPaymentHandler>
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
            $this->connection,
        );

        $abstract = $registry->getPaymentMethodHandler($this->ids->get(AbstractPaymentHandler::class));
        static::assertInstanceOf(AbstractPaymentHandler::class, $abstract);

        $foo = $registry->getPaymentMethodHandler(Uuid::randomHex());
        static::assertNull($foo);
    }

    public function testRegistryWithNonPaymentInterfaceService(): void
    {
        $registry = new PaymentHandlerRegistry(
            new ServiceLocator([
                AbstractPaymentHandler::class => fn () => new class {
                },
            ]),
            $this->connection,
        );

        $handler = $registry->getPaymentMethodHandler($this->ids->get(AbstractPaymentHandler::class));
        static::assertNull($handler);
    }

    public function testRegistryWithNonRegisteredPaymentHandler(): void
    {
        $this->registerHandler(AbstractPaymentHandler::class);

        $registry = new PaymentHandlerRegistry(
            new ServiceLocator([]),
            $this->connection,
        );

        $sync = $registry->getPaymentMethodHandler($this->ids->get(AbstractPaymentHandler::class));
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
            $connection,
        );

        $registry->getPaymentMethodHandler($uuid);
    }

    /**
     * @param class-string<AbstractPaymentHandler> $handler
     *
     * @return ServiceLocator<AbstractPaymentHandler>
     */
    private function registerHandler(string $handler): ServiceLocator
    {
        $class = new class extends AbstractPaymentHandler {
            public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
            {
                return false;
            }

            public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
            {
                return null;
            }
        };

        $this->registeredHandlers[Uuid::fromHexToBytes($this->ids->get($handler))] = $class;

        return new ServiceLocator([$class::class => fn () => $class]);
    }
}
