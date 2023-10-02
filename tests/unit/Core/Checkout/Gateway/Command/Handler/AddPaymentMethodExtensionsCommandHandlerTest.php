<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayException;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\AddPaymentMethodExtensionCommand;
use Shopware\Core\Checkout\Gateway\Command\Handler\AddPaymentMethodExtensionsCommandHandler;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(AddPaymentMethodExtensionsCommandHandler::class)]
#[Package('checkout')]
class AddPaymentMethodExtensionsCommandHandlerTest extends TestCase
{
    public function testSupportedCommands(): void
    {
        static::assertSame(
            [AddPaymentMethodExtensionCommand::class],
            AddPaymentMethodExtensionsCommandHandler::supportedCommands()
        );
    }

    public function testHandler(): void
    {
        $command = new AddPaymentMethodExtensionCommand('test', 'foo_key', ['foo' => 'bar', 1 => 2]);

        $payment1 = new PaymentMethodEntity();
        $payment1->setUniqueIdentifier(Uuid::randomHex());
        $payment1->setTechnicalName('test');

        $payment2 = new PaymentMethodEntity();
        $payment2->setUniqueIdentifier(Uuid::randomHex());
        $payment2->setTechnicalName('foo_bar');

        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection([$payment1, $payment2]),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $handler = new AddPaymentMethodExtensionsCommandHandler($this->createMock(ExceptionLogger::class));
        $handler->handle($command, $response, Generator::createSalesChannelContext());

        static::assertCount(2, $response->getAvailablePaymentMethods());

        $payment1 = $response->getAvailablePaymentMethods()->get($payment1->getUniqueIdentifier());
        static::assertNotNull($payment1);

        $payment2 = $response->getAvailablePaymentMethods()->get($payment2->getUniqueIdentifier());
        static::assertNotNull($payment2);

        $expected = new ArrayStruct(['foo' => 'bar', 1 => 2]);

        static::assertEquals(['foo_key' => $expected], $payment1->getExtensions());
        static::assertEmpty($payment2->getExtensions());
    }

    public function testUnknownMethodIsLogged(): void
    {
        $command = new AddPaymentMethodExtensionCommand('test', 'foo_key', ['foo' => 'bar', 1 => 2]);

        $payment1 = new PaymentMethodEntity();
        $payment1->setUniqueIdentifier(Uuid::randomHex());
        $payment1->setTechnicalName('foo_bar');

        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection([$payment1]),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects(static::once())
            ->method('logOrThrowException')
            ->with(
                static::callback(
                    static function (CheckoutGatewayException $exception): bool {
                        static::assertSame('Payment method "test" not found', $exception->getMessage());
                        static::assertSame(['technicalName' => 'test'], $exception->getParameters());

                        return true;
                    }
                )
            );

        $handler = new AddPaymentMethodExtensionsCommandHandler($logger);
        $handler->handle($command, $response, Generator::createSalesChannelContext());
    }
}
