<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Gateway\Command\AddPaymentMethodCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(AddPaymentMethodCommand::class)]
#[Package('checkout')]
class AddPaymentMethodCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = new AddPaymentMethodCommand('test');

        static::assertSame('test', $command->paymentMethodTechnicalName);
    }

    public function testCommandKey(): void
    {
        static::assertSame(AddPaymentMethodCommand::COMMAND_KEY, AddPaymentMethodCommand::getDefaultKeyName());
    }
}
