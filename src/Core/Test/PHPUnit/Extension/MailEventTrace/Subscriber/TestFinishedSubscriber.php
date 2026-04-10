<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\MailEventTrace\Subscriber;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\MailEventTrace\MailEventTracer;

/**
 * @internal
 */
#[Package('framework')]
class TestFinishedSubscriber implements FinishedSubscriber
{
    public function __construct(private readonly MailEventTracer $tracer)
    {
    }

    public function notify(Finished $event): void
    {
        $this->tracer->collectAndUninstall($event->test()->id());
    }
}
