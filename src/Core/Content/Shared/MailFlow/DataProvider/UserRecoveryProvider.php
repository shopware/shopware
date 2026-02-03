<?php declare(strict_types=1);

namespace Shopware\Core\Content\Shared\MailFlow\DataProvider;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;

/**
 * @internal
 *
 * @method UserRecoveryEntity|null getData(string $entityId, Context $context)
 */
#[Package('after-sales')]
class UserRecoveryProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return UserRecoveryDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociation('user');

        return $criteria;
    }
}
