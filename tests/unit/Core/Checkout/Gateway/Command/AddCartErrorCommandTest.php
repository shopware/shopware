<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Gateway\Command\AddCartErrorCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(AddCartErrorCommand::class)]
#[Package('checkout')]
class AddCartErrorCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = new AddCartErrorCommand('test', true, 1);

        static::assertSame('test', $command->message);
        static::assertTrue($command->blocking);
        static::assertSame(1, $command->level);
    }

    public function testCommandDefaults(): void
    {
        $command = new AddCartErrorCommand('foo');
        $command->assign(['message' => 'foo']);

        static::assertSame('foo', $command->message);
        static::assertSame(Error::LEVEL_WARNING, $command->level);
        static::assertFalse($command->blocking);
    }

    public function testCommandKey(): void
    {
        static::assertSame(AddCartErrorCommand::COMMAND_KEY, AddCartErrorCommand::getDefaultKeyName());
    }
}
