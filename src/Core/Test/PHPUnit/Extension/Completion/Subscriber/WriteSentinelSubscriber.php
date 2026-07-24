<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\Completion\Subscriber;

use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class WriteSentinelSubscriber implements ExecutionFinishedSubscriber
{
    public function __construct(private readonly string $path)
    {
    }

    public function notify(ExecutionFinished $event): void
    {
        file_put_contents($this->path, 'test runner execution finished');
    }
}
