<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\Handler\RemovePaymentMethodCommandHandler;
use Shopware\Core\Checkout\Gateway\Command\RemovePaymentMethodCommand;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(RemovePaymentMethodCommandHandler::class)]
#[Package('checkout')]
class RemovePaymentMethodCommandHandlerTest extends TestCase
{
    public function testSupportedCommands(): void
    {
        static::assertSame(
            [RemovePaymentMethodCommand::class],
            RemovePaymentMethodCommandHandler::supportedCommands()
        );
    }

    public function testHandle(): void
    {
        $paymentMethod1 = new PaymentMethodEntity();
        $paymentMethod1->setUniqueIdentifier(Uuid::randomHex());
        $paymentMethod1->setTechnicalName('test-1');

        $paymentMethod2 = new PaymentMethodEntity();
        $paymentMethod2->setUniqueIdentifier(Uuid::randomHex());
        $paymentMethod2->setTechnicalName('test-2');

        $paymentMethods = new PaymentMethodCollection([$paymentMethod1, $paymentMethod2]);

        $response = new CheckoutGatewayResponse(
            $paymentMethods,
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $command = new RemovePaymentMethodCommand('test-1');

        $handler = new RemovePaymentMethodCommandHandler();
        $handler->handle($command, $response, Generator::createSalesChannelContext());

        static::assertCount(1, $response->getAvailablePaymentMethods());
        static::assertNotNull($response->getAvailablePaymentMethods()->first());
        static::assertSame('test-2', $response->getAvailablePaymentMethods()->first()->getTechnicalName());
    }
}
