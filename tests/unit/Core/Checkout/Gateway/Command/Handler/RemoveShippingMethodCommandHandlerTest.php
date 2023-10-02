<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\Handler\RemoveShippingMethodCommandHandler;
use Shopware\Core\Checkout\Gateway\Command\RemoveShippingMethodCommand;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(RemoveShippingMethodCommandHandler::class)]
#[Package('checkout')]
class RemoveShippingMethodCommandHandlerTest extends TestCase
{
    public function testSupportedCommands(): void
    {
        static::assertSame(
            [RemoveShippingMethodCommand::class],
            RemoveShippingMethodCommandHandler::supportedCommands()
        );
    }

    public function testHandle(): void
    {
        $shippingMethod1 = new ShippingMethodEntity();
        $shippingMethod1->setUniqueIdentifier(Uuid::randomHex());
        $shippingMethod1->setTechnicalName('test-1');

        $shippingMethod2 = new ShippingMethodEntity();
        $shippingMethod2->setUniqueIdentifier(Uuid::randomHex());
        $shippingMethod2->setTechnicalName('test-2');

        $shippingMethods = new ShippingMethodCollection([$shippingMethod1, $shippingMethod2]);

        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection(),
            $shippingMethods,
            new ErrorCollection()
        );

        $command = new RemoveShippingMethodCommand('test-1');

        $handler = new RemoveShippingMethodCommandHandler();
        $handler->handle($command, $response, Generator::createSalesChannelContext());

        static::assertCount(1, $response->getAvailableShippingMethods());
        static::assertNotNull($response->getAvailableShippingMethods()->first());
        static::assertSame('test-2', $response->getAvailableShippingMethods()->first()->getTechnicalName());
    }
}
