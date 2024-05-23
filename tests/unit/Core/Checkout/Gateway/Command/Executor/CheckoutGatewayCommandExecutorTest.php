<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command\Executor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayException;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopware\Core\Checkout\Gateway\Command\CheckoutGatewayCommandCollection;
use Shopware\Core\Checkout\Gateway\Command\Executor\CheckoutGatewayCommandExecutor;
use Shopware\Core\Checkout\Gateway\Command\Registry\CheckoutGatewayCommandRegistry;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\TestCheckoutGatewayCommand;
use Shopware\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\TestCheckoutGatewayHandler;

/**
 * @internal
 */
#[CoversClass(CheckoutGatewayCommandExecutor::class)]
#[Package('checkout')]
class CheckoutGatewayCommandExecutorTest extends TestCase
{
    public function testExecute(): void
    {
        $logger = new ExceptionLogger('prod', false, $this->createMock(LoggerInterface::class));

        $handler = new TestCheckoutGatewayHandler();
        $registry = new CheckoutGatewayCommandRegistry([$handler]);
        $executor = new CheckoutGatewayCommandExecutor($registry, $logger);

        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $commands = new CheckoutGatewayCommandCollection([new TestCheckoutGatewayCommand(['test-1'])]);
        $response = $executor->execute($commands, $response, Generator::createSalesChannelContext());

        static::assertCount(1, $response->getAvailablePaymentMethods());
        static::assertNotNull($response->getAvailablePaymentMethods()->first());
        static::assertSame('test-1', $response->getAvailablePaymentMethods()->first()->getTechnicalName());
    }

    public function testUnknownCommandThrowsIfEnforced(): void
    {
        $logger = new ExceptionLogger('prod', true, $this->createMock(LoggerInterface::class));

        $handler = new TestCheckoutGatewayHandler();
        $registry = new CheckoutGatewayCommandRegistry([$handler]);
        $executor = new CheckoutGatewayCommandExecutor($registry, $logger);

        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $throwCommand = new class() extends AbstractCheckoutGatewayCommand {
            public static function getDefaultKeyName(): string
            {
                return 'this-one-throws';
            }
        };

        $commands = new CheckoutGatewayCommandCollection([
            new TestCheckoutGatewayCommand(['test-1']),
            $throwCommand,
        ]);

        static::expectException(CheckoutGatewayException::class);
        static::expectExceptionMessage('Handler not found for command "this-one-throws"');

        $executor->execute($commands, $response, Generator::createSalesChannelContext());
    }

    public function testUnknownCommandLogsInProd(): void
    {
        $psrLogger = $this->createMock(LoggerInterface::class);
        $psrLogger
            ->expects(static::once())
            ->method('log')
            ->with(LogLevel::ERROR, 'Handler not found for command "this-one-throws"');

        $logger = new ExceptionLogger('prod', false, $psrLogger);

        $handler = new TestCheckoutGatewayHandler();
        $registry = new CheckoutGatewayCommandRegistry([$handler]);
        $executor = new CheckoutGatewayCommandExecutor($registry, $logger);

        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $throwCommand = new class() extends AbstractCheckoutGatewayCommand {
            public static function getDefaultKeyName(): string
            {
                return 'this-one-throws';
            }
        };

        $commands = new CheckoutGatewayCommandCollection([
            new TestCheckoutGatewayCommand(['test-1']),
            $throwCommand,
        ]);

        $executor->execute($commands, $response, Generator::createSalesChannelContext());
    }
}
