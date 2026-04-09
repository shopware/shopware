<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\RequestStackDirty\Subscriber;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\RequestStackDirty\StackDepth;

/**
 * @internal
 */
#[Package('framework')]
class TestFinishedSubscriber implements FinishedSubscriber
{
    public function __construct(private readonly StackDepth $stackDepth)
    {
    }

    public function notify(Finished $event): void
    {
        $after = TestPreparationStartedSubscriber::currentDepth();

        if ($after > $this->stackDepth->before) {
            echo \PHP_EOL . \sprintf(
                '[RequestStack dirty] %s left %d unreleased request(s) on the stack (was %d, now %d)',
                $event->test()->id(),
                $after - $this->stackDepth->before,
                $this->stackDepth->before,
                $after,
            ) . \PHP_EOL;
        }
    }
}
