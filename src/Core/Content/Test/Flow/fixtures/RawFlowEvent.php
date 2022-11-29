<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow\fixtures;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 *
 * @internal
 */
class RawFlowEvent implements FlowEventAware
{
    protected ?Context $context;

    public function __construct(?Context $context = null)
    {
        $this->context = $context;
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
