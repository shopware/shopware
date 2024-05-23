<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Gateway\Command\RemoveShippingMethodCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(RemoveShippingMethodCommand::class)]
#[Package('checkout')]
class RemoveShippingMethodCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = new RemoveShippingMethodCommand('test');

        static::assertSame('test', $command->shippingMethodTechnicalName);
    }

    public function testCommandKey(): void
    {
        static::assertSame(RemoveShippingMethodCommand::COMMAND_KEY, RemoveShippingMethodCommand::getDefaultKeyName());
    }
}
