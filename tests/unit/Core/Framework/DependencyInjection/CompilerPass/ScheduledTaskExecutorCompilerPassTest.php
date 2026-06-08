<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ScheduledTaskExecutorCompilerPass;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskExecutor;
use Shopware\Tests\Integration\Core\Framework\MessageQueue\fixtures\DummyScheduledTaskHandler;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(ScheduledTaskExecutorCompilerPass::class)]
class ScheduledTaskExecutorCompilerPassTest extends TestCase
{
    public function testInjectsExecutorIntoScheduledTaskHandlers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ScheduledTaskExecutor::class, new Definition(ScheduledTaskExecutor::class));

        $handler = new Definition(DummyScheduledTaskHandler::class);
        $handler->addTag('messenger.message_handler');
        $container->setDefinition(DummyScheduledTaskHandler::class, $handler);

        (new ScheduledTaskExecutorCompilerPass())->process($container);

        $methodCalls = $container->getDefinition(DummyScheduledTaskHandler::class)->getMethodCalls();

        static::assertCount(1, $methodCalls);
        static::assertSame('setScheduledTaskExecutor', $methodCalls[0][0]);
        static::assertSame(ScheduledTaskExecutor::class, (string) $methodCalls[0][1][0]);
    }

    public function testIgnoresHandlersThatAreNotScheduledTaskHandlers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ScheduledTaskExecutor::class, new Definition(ScheduledTaskExecutor::class));

        $handler = new Definition(NullLogger::class);
        $handler->addTag('messenger.message_handler');
        $container->setDefinition('some.other.handler', $handler);

        (new ScheduledTaskExecutorCompilerPass())->process($container);

        static::assertSame([], $container->getDefinition('some.other.handler')->getMethodCalls());
    }

    public function testDoesNothingWhenExecutorIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        $handler = new Definition(DummyScheduledTaskHandler::class);
        $handler->addTag('messenger.message_handler');
        $container->setDefinition(DummyScheduledTaskHandler::class, $handler);

        (new ScheduledTaskExecutorCompilerPass())->process($container);

        static::assertSame([], $container->getDefinition(DummyScheduledTaskHandler::class)->getMethodCalls());
    }
}
