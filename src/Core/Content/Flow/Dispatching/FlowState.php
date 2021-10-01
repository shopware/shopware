<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @internal (flag:FEATURE_NEXT_8225)
 */
class FlowState
{
    public string $flowId;

    public string $sequenceId;

    public bool $stop = false;

    public FlowEventAware $event;

    public function __construct(FlowEventAware $event)
    {
        $this->event = $event;
    }
}
