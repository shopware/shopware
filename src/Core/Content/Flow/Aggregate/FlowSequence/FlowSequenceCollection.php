<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Aggregate\FlowSequence;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<FlowSequenceEntity>
 */
#[Package('business-ops')]
class FlowSequenceCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'flow_sequence_collection';
    }

    protected function getExpectedClass(): string
    {
        return FlowSequenceEntity::class;
    }
}
