<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Events;

use Shopware\Core\Content\Flow\Api\FlowActionCollectorResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class FlowActionCollectorEvent extends NestedEvent
{
    private FlowActionCollectorResponse $flowActionCollectorResponse;

    private Context $context;

    public function __construct(FlowActionCollectorResponse $flowActionCollectorResponse, Context $context)
    {
        $this->flowActionCollectorResponse = $flowActionCollectorResponse;
        $this->context = $context;
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
