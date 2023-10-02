<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Gateway\Command\Registry\CheckoutGatewayCommandRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\TestCheckoutGatewayCommand;
use Shopware\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\TestCheckoutGatewayFooCommand;
use Shopware\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\TestCheckoutGatewayHandler;

/**
 * @internal
 */
#[CoversClass(CheckoutGatewayCommandRegistry::class)]
#[Package('checkout')]
class CheckoutGatewayCommandRegistryTest extends TestCase
{
    public function testConstruct(): void
    {
        $handler = new TestCheckoutGatewayHandler();
        $registry = new CheckoutGatewayCommandRegistry([$handler]);

        static::assertTrue($registry->has(TestCheckoutGatewayCommand::COMMAND_KEY));
        static::assertTrue($registry->has(TestCheckoutGatewayFooCommand::COMMAND_KEY));
        static::assertFalse($registry->has('not-existing-key'));

        static::assertSame($handler, $registry->get(TestCheckoutGatewayCommand::COMMAND_KEY));
        static::assertSame($handler, $registry->get(TestCheckoutGatewayFooCommand::COMMAND_KEY));

        static::assertTrue($registry->hasAppCommand(TestCheckoutGatewayCommand::COMMAND_KEY));
        static::assertTrue($registry->hasAppCommand(TestCheckoutGatewayFooCommand::COMMAND_KEY));
        static::assertFalse($registry->hasAppCommand('not-existing-key'));

        static::assertSame(TestCheckoutGatewayCommand::class, $registry->getAppCommand(TestCheckoutGatewayCommand::COMMAND_KEY));
        static::assertSame(TestCheckoutGatewayFooCommand::class, $registry->getAppCommand(TestCheckoutGatewayFooCommand::COMMAND_KEY));
    }

    public function testAll(): void
    {
        $handler = new TestCheckoutGatewayHandler();
        $registry = new CheckoutGatewayCommandRegistry([$handler]);

        static::assertSame(
            [
                TestCheckoutGatewayCommand::COMMAND_KEY => $handler,
                TestCheckoutGatewayFooCommand::COMMAND_KEY => $handler,
            ],
            $registry->all()
        );
    }
}
