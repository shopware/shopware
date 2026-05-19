<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal not intended for decoration or replacement
 *
 * @phpstan-type FlowHolder array{id: string, name: string, payload: Flow}
 * @phpstan-type EventGroupedFlowHolders array<string, array<FlowHolder>>
 */
#[Package('after-sales')]
abstract class AbstractFlowLoader
{
    /**
     * @return EventGroupedFlowHolders
     */
    abstract public function load(): array;
}
