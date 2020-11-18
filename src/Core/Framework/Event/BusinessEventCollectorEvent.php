<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Context;

class BusinessEventCollectorEvent extends NestedEvent
{
    public const NAME = 'collect.business-events';

    /**
     * @var BusinessEventCollectorResponse
     */
    private $events;

    /**
     * @var Context
     */
    private $context;

    public function __construct(BusinessEventCollectorResponse $events, Context $context)
    {
        $this->events = $events;
        $this->context = $context;
    }

    public function getCollection(): BusinessEventCollectorResponse
    {
        return $this->events;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
