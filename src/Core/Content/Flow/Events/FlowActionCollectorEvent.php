<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Events;

use Shopware\Core\Content\Flow\Api\FlowActionCollectorResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class FlowActionCollectorEvent extends NestedEvent
{
    public function __construct(
        private readonly FlowActionCollectorResponse $flowActionCollectorResponse,
        private readonly Context $context
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCollection(): FlowActionCollectorResponse
    {
        return $this->flowActionCollectorResponse;
    }
}
