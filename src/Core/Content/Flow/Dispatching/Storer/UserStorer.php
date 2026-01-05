<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Content\Shared\MailFlow\UserRecoveryCriteriaBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\UserAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class UserStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<UserRecoveryCollection> $userRecoveryRepository
     */
    public function __construct(
        private readonly EntityRepository $userRecoveryRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly UserRecoveryCriteriaBuilder $userRecoveryCriteriaBuilder,
    ) {
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
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?UserRecoveryEntity
    {
        $id = $storableFlow->getStore(UserAware::USER_RECOVERY_ID);
        if ($id === null) {
            return null;
        }

        $criteria = $this->userRecoveryCriteriaBuilder->getCriteria($id, $storableFlow->getContext());

        return $this->loadUserRecovery($criteria, $storableFlow->getContext(), $id);
    }

    private function loadUserRecovery(Criteria $criteria, Context $context, string $id): ?UserRecoveryEntity
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $event = new BeforeLoadStorableFlowDataEvent(
                UserRecoveryDefinition::ENTITY_NAME,
                $criteria,
                $context,
            );

            $this->dispatcher->dispatch($event, $event->getName());
        }

        $user = $this->userRecoveryRepository->search($criteria, $context)->getEntities()->get($id);

        if ($user) {
            return $user;
        }

        return null;
    }
}
