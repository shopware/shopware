<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture\StubContextGatewayCommand;
use Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture\StubContextGatewayFooCommand;
use Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture\StubContextGatewayHandler;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextGatewayCommandRegistry::class)]
class ContextGatewayCommandRegistryTest extends TestCase
{
    public function testRegistry(): void
    {
        $handler = new StubContextGatewayHandler();
        $registry = new ContextGatewayCommandRegistry([$handler]);

        static::assertTrue($registry->has(StubContextGatewayCommand::COMMAND_KEY));
        static::assertTrue($registry->has(StubContextGatewayFooCommand::COMMAND_KEY));
        static::assertFalse($registry->has('not-existing-key'));

        static::assertSame($handler, $registry->get(StubContextGatewayCommand::COMMAND_KEY));
        static::assertSame($handler, $registry->get(StubContextGatewayFooCommand::COMMAND_KEY));

        static::assertTrue($registry->hasAppCommand(StubContextGatewayCommand::COMMAND_KEY));
        static::assertTrue($registry->hasAppCommand(StubContextGatewayFooCommand::COMMAND_KEY));
        static::assertFalse($registry->hasAppCommand('not-existing-key'));

        static::assertSame(StubContextGatewayCommand::class, $registry->getAppCommand(StubContextGatewayCommand::COMMAND_KEY));
        static::assertSame(StubContextGatewayFooCommand::class, $registry->getAppCommand(StubContextGatewayFooCommand::COMMAND_KEY));
    }

    public function testAll(): void
    {
        $handler = new StubContextGatewayHandler();
        $registry = new ContextGatewayCommandRegistry([$handler]);

        static::assertSame(
            [
                StubContextGatewayCommand::COMMAND_KEY => $handler,
                StubContextGatewayFooCommand::COMMAND_KEY => $handler,
            ],
            $registry->all()
        );
    }
}
