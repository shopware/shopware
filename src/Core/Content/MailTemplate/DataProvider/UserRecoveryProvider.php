<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\DataProvider;

use Shopware\Core\Content\Shared\MailFlow\UserRecoveryCriteriaBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;

/**
 * @internal
 */
#[Package('after-sales')]
class UserRecoveryProvider implements DataProvider
{
    /**
     * @param EntityRepository<UserRecoveryCollection> $userRecoveryRepository
     */
    public function __construct(
        private readonly EntityRepository $userRecoveryRepository,
        private readonly UserRecoveryCriteriaBuilder $userRecoveryCriteriaBuilder,
    ) {
    }

    public function getData(string $entityId, Context $context): ?UserRecoveryEntity
    {
        $criteria = $this->userRecoveryCriteriaBuilder->getCriteria($entityId, $context);

        return $this->userRecoveryRepository->search($criteria, $context)->getEntities()->get($entityId);
    }
}
