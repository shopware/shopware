<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<BusinessEventDefinition>
 */
#[Package('business-ops')]
class BusinessEventCollectorResponse extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return BusinessEventDefinition::class;
    }
}
