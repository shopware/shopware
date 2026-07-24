<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\Completion\Subscriber;

use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

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
        (new Filesystem())->dumpFile($this->path, 'test runner execution finished');
    }
}
