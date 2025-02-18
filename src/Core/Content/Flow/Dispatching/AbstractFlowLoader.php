<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal not intended for decoration or replacement
 *
 * @phpstan-type TFlow array{id: string, name: string, payload: Flow}
 * @phpstan-type TFlows array<TFlow>
 * @phpstan-type EventGroupedTFlows array<string, TFlow>
 */
#[Package('after-sales')]
abstract class AbstractFlowLoader
{
    /**
     * @return EventGroupedTFlows
     */
    abstract public function load(): array;
}
