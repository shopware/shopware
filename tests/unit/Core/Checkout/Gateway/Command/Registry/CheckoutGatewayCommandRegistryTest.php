<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Gateway\Command\Registry\CheckoutGatewayCommandRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\StubCheckoutGatewayCommand;
use Shopware\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\StubCheckoutGatewayFooCommand;
use Shopware\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\StubCheckoutGatewayHandler;

/**
 * @internal
 */
#[CoversClass(CheckoutGatewayCommandRegistry::class)]
#[Package('checkout')]
class CheckoutGatewayCommandRegistryTest extends TestCase
{
    public function testConstruct(): void
    {
        $handler = new StubCheckoutGatewayHandler();
        $registry = new CheckoutGatewayCommandRegistry([$handler]);

        static::assertTrue($registry->has(StubCheckoutGatewayCommand::COMMAND_KEY));
        static::assertTrue($registry->has(StubCheckoutGatewayFooCommand::COMMAND_KEY));
        static::assertFalse($registry->has('not-existing-key'));

        static::assertSame($handler, $registry->get(StubCheckoutGatewayCommand::COMMAND_KEY));
        static::assertSame($handler, $registry->get(StubCheckoutGatewayFooCommand::COMMAND_KEY));

        static::assertTrue($registry->hasAppCommand(StubCheckoutGatewayCommand::COMMAND_KEY));
        static::assertTrue($registry->hasAppCommand(StubCheckoutGatewayFooCommand::COMMAND_KEY));
        static::assertFalse($registry->hasAppCommand('not-existing-key'));

        static::assertSame(StubCheckoutGatewayCommand::class, $registry->getAppCommand(StubCheckoutGatewayCommand::COMMAND_KEY));
        static::assertSame(StubCheckoutGatewayFooCommand::class, $registry->getAppCommand(StubCheckoutGatewayFooCommand::COMMAND_KEY));
    }

    public function testAll(): void
    {
        $handler = new StubCheckoutGatewayHandler();
        $registry = new CheckoutGatewayCommandRegistry([$handler]);

        static::assertSame(
            [
                StubCheckoutGatewayCommand::COMMAND_KEY => $handler,
                StubCheckoutGatewayFooCommand::COMMAND_KEY => $handler,
            ],
            $registry->all()
        );
    }
}
