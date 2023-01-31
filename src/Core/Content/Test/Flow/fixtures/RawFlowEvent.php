<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow\fixtures;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('business-ops')]
class RawFlowEvent implements FlowEventAware
{
    public function __construct(protected ?Context $context = null)
    {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return 'raw_flow.event';
    }

    public function getContext(): Context
    {
        return $this->context ?? Context::createDefaultContext();
    }
}
