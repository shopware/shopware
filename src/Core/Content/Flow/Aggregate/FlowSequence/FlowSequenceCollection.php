<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Aggregate\FlowSequence;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package business-ops
 * @extends EntityCollection<FlowSequenceEntity>
 */
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
