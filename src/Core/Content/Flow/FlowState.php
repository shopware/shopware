<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Shopware\Core\Framework\Event\BusinessEventInterface;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class FlowState
{
    public bool $stop = false;

    public BusinessEventInterface $event;

    public function __construct(BusinessEventInterface $event)
    {
        $this->event = $event;
    }
}
