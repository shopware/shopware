<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\MailEventTrace\Subscriber;

use PHPUnit\Event\Test\BeforeTestMethodCalled;
use PHPUnit\Event\Test\BeforeTestMethodCalledSubscriber as BeforeTestMethodCalledSubscriberInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\MailEventTrace\MailEventTracer;

/**
 * @internal
 */
#[Package('framework')]
class BeforeTestMethodCalledSubscriber implements BeforeTestMethodCalledSubscriberInterface
{
    public function __construct(private readonly MailEventTracer $tracer)
    {
    }

    public function notify(BeforeTestMethodCalled $event): void
    {
        $this->tracer->install();
    }
}
