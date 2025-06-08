<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture\TestContextGatewayCommand;
use Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture\TestContextGatewayFooCommand;
use Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture\TestContextGatewayHandler;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextGatewayCommandRegistry::class)]
class ContextGatewayCommandRegistryTest extends TestCase
{
    public function testRegistry(): void
    {
        $handler = new TestContextGatewayHandler();
        $registry = new ContextGatewayCommandRegistry([$handler]);

        static::assertTrue($registry->has(TestContextGatewayCommand::COMMAND_KEY));
        static::assertTrue($registry->has(TestContextGatewayFooCommand::COMMAND_KEY));
        static::assertFalse($registry->has('not-existing-key'));

        static::assertSame($handler, $registry->get(TestContextGatewayCommand::COMMAND_KEY));
        static::assertSame($handler, $registry->get(TestContextGatewayFooCommand::COMMAND_KEY));

        static::assertTrue($registry->hasAppCommand(TestContextGatewayCommand::COMMAND_KEY));
        static::assertTrue($registry->hasAppCommand(TestContextGatewayFooCommand::COMMAND_KEY));
        static::assertFalse($registry->hasAppCommand('not-existing-key'));

        static::assertSame(TestContextGatewayCommand::class, $registry->getAppCommand(TestContextGatewayCommand::COMMAND_KEY));
        static::assertSame(TestContextGatewayFooCommand::class, $registry->getAppCommand(TestContextGatewayFooCommand::COMMAND_KEY));
    }

    public function testAll(): void
    {
        $handler = new TestContextGatewayHandler();
        $registry = new ContextGatewayCommandRegistry([$handler]);

        static::assertSame(
            [
                TestContextGatewayCommand::COMMAND_KEY => $handler,
                TestContextGatewayFooCommand::COMMAND_KEY => $handler,
            ],
            $registry->all()
        );
    }
}
