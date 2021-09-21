<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Api;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                      add(FlowActionDefinition $entity)
 * @method void                      set(string $key, FlowActionDefinition $entity)
 * @method FlowActionDefinition[]    getIterator()
 * @method FlowActionDefinition[]    getElements()
 * @method FlowActionDefinition|null get(string $key)
 * @method FlowActionDefinition|null first()
 * @method FlowActionDefinition|null last()
 */
class FlowActionCollectorResponse extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return FlowActionDefinition::class;
    }
}
