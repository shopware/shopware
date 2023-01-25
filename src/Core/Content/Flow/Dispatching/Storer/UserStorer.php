<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\UserAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;

#[Package('business-ops')]
class UserStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $userRecoveryRepository)
    {
    }

    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof UserAware || isset($stored[UserAware::USER_RECOVERY_ID])) {
            return $stored;
        }

        $stored[UserAware::USER_RECOVERY_ID] = $event->getUserId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(UserAware::USER_RECOVERY_ID)) {
            return;
        }

        $storable->lazy(
            UserAware::USER_RECOVERY,
            $this->load(...),
            [$storable->getStore(UserAware::USER_RECOVERY_ID), $storable->getContext()]
        );
    }

    /**
     * @param array<int, mixed> $args
     */
    public function load(array $args): ?UserRecoveryEntity
    {
        [$id, $context] = $args;

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('user');

        $user = $this->userRecoveryRepository->search($criteria, $context)->get($id);

        if ($user) {
            /** @var UserRecoveryEntity $user */
            return $user;
        }

        return null;
    }
}
