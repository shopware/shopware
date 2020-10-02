<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                         add(BusinessEventDefinition $entity)
 * @method void                         set(string $key, BusinessEventDefinition $entity)
 * @method BusinessEventDefinition[]    getIterator()
 * @method BusinessEventDefinition[]    getElements()
 * @method BusinessEventDefinition|null get(string $key)
 * @method BusinessEventDefinition|null first()
 * @method BusinessEventDefinition|null last()
 */
class BusinessEventCollectorResponse extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return BusinessEventDefinition::class;
    }
}
