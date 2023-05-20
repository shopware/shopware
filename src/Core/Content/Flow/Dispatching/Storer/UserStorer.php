<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\UserAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('business-ops')]
class UserStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $userRecoveryRepository,
        private readonly EventDispatcherInterface $dispatcher
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

    /**
     * @param array<int, mixed> $args
     *
     * @deprecated tag:v6.6.0 - Will be removed in v6.6.0.0
     */
    public function load(array $args): ?UserRecoveryEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6_6_0_0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0.0')
        );

        [$id, $context] = $args;

        $criteria = new Criteria([$id]);

        return $this->loadUserRecovery($criteria, $context, $id);
    }

    private function lazyLoad(StorableFlow $storableFlow): ?UserRecoveryEntity
    {
        $id = $storableFlow->getStore(UserAware::USER_RECOVERY_ID);
        if ($id === null) {
            return null;
        }

        $criteria = new Criteria([$id]);

        return $this->loadUserRecovery($criteria, $storableFlow->getContext(), $id);
    }

    private function loadUserRecovery(Criteria $criteria, Context $context, string $id): ?UserRecoveryEntity
    {
        $criteria->addAssociation('user');

        $event = new BeforeLoadStorableFlowDataEvent(
            UserRecoveryDefinition::ENTITY_NAME,
            $criteria,
            $context,
        );

        $this->dispatcher->dispatch($event, $event->getName());

        $user = $this->userRecoveryRepository->search($criteria, $context)->get($id);

        if ($user) {
            /** @var UserRecoveryEntity $user */
            return $user;
        }

        return null;
    }
}
