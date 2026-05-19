<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeBillingAddressCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingAddressCommand;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\ChangeAddressCommandHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChangeAddressCommandHandler::class)]
class ChangeAddressCommandHandlerTest extends TestCase
{
    public function testHandleBillingAddressCommand(): void
    {
        $command = ChangeBillingAddressCommand::createFromPayload(['addressId' => 'billingAddressId']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $handler = new ChangeAddressCommandHandler();

        $handler->handle($command, $context, $parameters);

        static::assertSame(['billingAddressId' => 'billingAddressId'], $parameters);
    }

    public function testHandleShippingAddressCommand(): void
    {
        $command = ChangeShippingAddressCommand::createFromPayload(['addressId' => 'shippingAddressId']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $handler = new ChangeAddressCommandHandler();

        $handler->handle($command, $context, $parameters);

        static::assertSame(['shippingAddressId' => 'shippingAddressId'], $parameters);
    }

    public function testGetSupportedCommands(): void
    {
        static::assertSame([
            ChangeBillingAddressCommand::class,
            ChangeShippingAddressCommand::class,
        ], ChangeAddressCommandHandler::supportedCommands());
    }
}
