<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\MailEventTrace\Subscriber;

use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\MailEventTrace\MailEventTracer;

/**
 * @internal
 */
#[Package('framework')]
class TestPreparedSubscriber implements PreparedSubscriber
{
    public function __construct(private readonly MailEventTracer $tracer)
    {
    }

    public function notify(Prepared $event): void
    {
        $this->tracer->install($event->test()->id());
    }
}
