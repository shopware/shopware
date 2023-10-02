<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayException;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\AddShippingMethodExtensionCommand;
use Shopware\Core\Checkout\Gateway\Command\Handler\AddShippingMethodExtensionsCommandHandler;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(AddShippingMethodExtensionsCommandHandler::class)]
#[Package('checkout')]
class AddShippingMethodExtensionsCommandHandlerTest extends TestCase
{
    public function testSupportedCommands(): void
    {
        static::assertSame(
            [AddShippingMethodExtensionCommand::class],
            AddShippingMethodExtensionsCommandHandler::supportedCommands()
        );
    }

    public function testHandler(): void
    {
        $command = new AddShippingMethodExtensionCommand('test', 'foo_key', ['foo' => 'bar', 1 => 2]);

        $shipping1 = new ShippingMethodEntity();
        $shipping1->setUniqueIdentifier(Uuid::randomHex());
        $shipping1->setTechnicalName('test');

        $shipping2 = new ShippingMethodEntity();
        $shipping2->setUniqueIdentifier(Uuid::randomHex());
        $shipping2->setTechnicalName('foo_bar');

        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection([$shipping1, $shipping2]),
            new ErrorCollection()
        );

        $handler = new AddShippingMethodExtensionsCommandHandler($this->createMock(ExceptionLogger::class));
        $handler->handle($command, $response, Generator::createSalesChannelContext());

        static::assertCount(2, $response->getAvailableShippingMethods());

        $shipping1 = $response->getAvailableShippingMethods()->get($shipping1->getUniqueIdentifier());
        static::assertNotNull($shipping1);

        $shipping2 = $response->getAvailableShippingMethods()->get($shipping2->getUniqueIdentifier());
        static::assertNotNull($shipping2);

        $expected = new ArrayStruct(['foo' => 'bar', 1 => 2]);

        static::assertEquals(['foo_key' => $expected], $shipping1->getExtensions());
        static::assertEmpty($shipping2->getExtensions());
    }

    public function testUnknownMethodIsLogged(): void
    {
        $command = new AddShippingMethodExtensionCommand('test', 'foo_key', ['foo' => 'bar', 1 => 2]);

        $shipping1 = new ShippingMethodEntity();
        $shipping1->setUniqueIdentifier(Uuid::randomHex());
        $shipping1->setTechnicalName('foo_bar');

        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection([$shipping1]),
            new ErrorCollection()
        );

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects(static::once())
            ->method('logOrThrowException')
            ->with(
                static::callback(
                    static function (CheckoutGatewayException $exception): bool {
                        static::assertSame('Shipping method "test" not found', $exception->getMessage());
                        static::assertSame(['technicalName' => 'test'], $exception->getParameters());

                        return true;
                    }
                )
            );

        $handler = new AddShippingMethodExtensionsCommandHandler($logger);
        $handler->handle($command, $response, Generator::createSalesChannelContext());
    }
}
