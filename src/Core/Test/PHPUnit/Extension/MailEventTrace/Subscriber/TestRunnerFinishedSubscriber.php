<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\MailEventTrace\Subscriber;

use PHPUnit\Event\TestRunner\Finished;
use PHPUnit\Event\TestRunner\FinishedSubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\MailEventTrace\MailEventTracer;

/**
 * @internal
 */
#[Package('framework')]
class TestRunnerFinishedSubscriber implements FinishedSubscriber
{
    public function __construct(private readonly MailEventTracer $tracer)
    {
    }

    public function notify(Finished $event): void
    {
        $this->tracer->report();
    }
}
