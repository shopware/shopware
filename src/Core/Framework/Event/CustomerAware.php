<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

/**
 * @internal (flag:FEATURE_NEXT_8225)
 */
interface CustomerAware extends FlowEventAware
{
    public function getCustomerId(): string;
}
