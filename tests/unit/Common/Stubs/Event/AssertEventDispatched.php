<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Common\Stubs\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait AssertEventDispatched
{
    public static function assertDispatched(
        EventDispatcherInterface $dispatcher,
        TestCase $test,
        string $event,
        int $count = 1
    ): void {
        $listener = $test->getMockBuilder(CallableClass::class)->getMock();
        $listener
            ->expects(static::exactly($count))
            ->method('__invoke');

        $dispatcher->addListener($event, $listener);
    }
}
