<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void            add(FlowEntity $entity)
 * @method void            set(string $key, FlowEntity $entity)
 * @method FlowEntity[]    getIterator()
 * @method FlowEntity[]    getElements()
 * @method FlowEntity|null get(string $key)
 * @method FlowEntity|null first()
 * @method FlowEntity|null last()
 */
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
