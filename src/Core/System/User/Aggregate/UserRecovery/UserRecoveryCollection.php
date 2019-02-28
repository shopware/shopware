<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Aggregate\UserRecovery;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                    add(UserRecoveryEntity $entity)
 * @method UserRecoveryEntity[]    getIterator()
 * @method UserRecoveryEntity[]    getElements()
 * @method UserRecoveryEntity|null get(string $key)
 * @method UserRecoveryEntity|null first()
 * @method UserRecoveryEntity|null last()
 */
class UserRecoveryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return UserRecoveryEntity::class;
    }
}
