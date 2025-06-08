<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<FlowEntity>
 */
#[Package('after-sales')]
class FlowCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'flow_collection';
    }

    protected function getExpectedClass(): string
    {
        return FlowEntity::class;
    }
}
