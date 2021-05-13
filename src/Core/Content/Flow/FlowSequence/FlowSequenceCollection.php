<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\FlowSequence;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal (flag:FEATURE_NEXT_8225)
 *
 * @method void                    add(FlowSequenceEntity $entity)
 * @method void                    set(string $key, FlowSequenceEntity $entity)
 * @method FlowSequenceEntity[]    getIterator()
 * @method FlowSequenceEntity[]    getElements()
 * @method FlowSequenceEntity|null get(string $key)
 * @method FlowSequenceEntity|null first()
 * @method FlowSequenceEntity|null last()
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
