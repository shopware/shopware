<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\Datadog\Subscriber;

use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\Common\TimeKeeper;

/**
 * @internal
 */
#[Package('core')]
class TestPreparedSubscriber implements PreparedSubscriber
{
    public function __construct(private readonly TimeKeeper $timeKeeper)
    {
    }

    public function notify(Prepared $event): void
    {
        $this->timeKeeper->start(
            $event->test()->id(),
            $event->telemetryInfo()->time()
        );
    }
}
