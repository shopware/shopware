<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Events;

use Shopware\Core\Content\Flow\Action\FlowActionCollectorResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class FlowActionCollectorEvent extends NestedEvent
{
    public const NAME = 'collect.flow-action';

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
