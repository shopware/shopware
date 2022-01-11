<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\Dispatching\Struct\Sequence;
use Shopware\Core\Framework\Event\FlowEventAware;

class FlowState
{
    public string $flowId;

    /**
     * @deprecated tag:v6.5.0 Will be deleted. use getSequenceId() instead.
     */
    public string $sequenceId;

    public bool $stop = false;

    public FlowEventAware $event;

    public Sequence $currentSequence;

    public bool $delayed = false;

    public function __construct(FlowEventAware $event)
    {
        $this->event = $event;
    }

    public function getSequenceId(): string
    {
        return $this->currentSequence->sequenceId;
    }
}
