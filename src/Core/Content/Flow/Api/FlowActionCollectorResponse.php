<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<FlowActionDefinition>
 */
#[Package('business-ops')]
class FlowActionCollectorResponse extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return FlowActionDefinition::class;
    }
}
