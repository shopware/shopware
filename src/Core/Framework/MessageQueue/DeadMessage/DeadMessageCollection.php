<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\DeadMessage;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                   add(DeadMessageEntity $entity)
 * @method void                   set(string $key, DeadMessageEntity $entity)
 * @method DeadMessageEntity[]    getIterator()
 * @method DeadMessageEntity[]    getElements()
 * @method DeadMessageEntity|null get(string $key)
 * @method DeadMessageEntity|null first()
 * @method DeadMessageEntity|null last()
 */
class DeadMessageCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return DeadMessageEntity::class;
    }
}
