<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ActionEventDispatcher;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ActionEventDispatcherTest extends TestCase
{
    use KernelTestBehaviour;

    public function testAllEventsPassthru(): void
    {
        $context = Context::createDefaultContext();
        $event = new TestActionEvent($context);

        $mock = static::createMock(EventDispatcherInterface::class);
        $mock->expects(static::once())
            ->method('dispatch')->with($event->getName(), $event)
            ->willReturn($event);

        $dispatcher = new ActionEventDispatcher($mock);
        $dispatcher->dispatch($event->getName(), $event);
    }
}
