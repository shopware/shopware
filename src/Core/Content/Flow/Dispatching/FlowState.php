<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\Dispatching\Struct\Sequence;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;

class FlowState
{
    public string $flowId;

    /**
     * @deprecated tag:v6.5.0 Will be deleted. use getSequenceId() instead.
     */
    public string $sequenceId;

    public bool $stop = false;

    /**
     * @deprecated tag:v6.5.0 - Will be removed
     */
    public FlowEventAware $event;

    public Sequence $currentSequence;

    public bool $delayed = false;

    /**
     * @deprecated tag:v6.5.0 - Will be removed
     */
    public function __construct(?FlowEventAware $event = null)
    {
        if (!Feature::isActive('v6.5.0.0') && $event === null) {
            throw new \RuntimeException('Prior to v6.5.0.0 a FlowEventAware needs to be passed to the FlowStates constructor');
        }

        if ($event !== null) {
            $this->event = $event;
        }
    }

    public function getSequenceId(): string
    {
        return $this->currentSequence->sequenceId;
    }
}
