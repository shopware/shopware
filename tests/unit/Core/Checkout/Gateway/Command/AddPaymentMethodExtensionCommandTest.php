<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Gateway\Command\AddPaymentMethodExtensionCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(AddPaymentMethodExtensionCommand::class)]
#[Package('checkout')]
class AddPaymentMethodExtensionCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = new AddPaymentMethodExtensionCommand('test', 'foo', ['foo' => 'bar', 1 => 2]);

        static::assertSame('test', $command->paymentMethodTechnicalName);
        static::assertSame('foo', $command->extensionKey);
        static::assertSame(['foo' => 'bar', 1 => 2], $command->extensionsPayload);
    }

    public function testCommandKey(): void
    {
        static::assertSame(AddPaymentMethodExtensionCommand::COMMAND_KEY, AddPaymentMethodExtensionCommand::getDefaultKeyName());
    }
}
