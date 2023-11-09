<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Common\Stubs\Flow;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @deprecated tag:v6.6.0 - Will be removed. Use \Shopware\Core\Test\Stub\Flow\DummyEvent instead
 */
#[Package('core')]
class DummyEvent extends Event implements FlowEventAware
{
    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return 'dummy.event';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }
}
