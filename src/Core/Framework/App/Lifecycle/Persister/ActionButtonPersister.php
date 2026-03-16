<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonCollection;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ActionButtonPersister implements PersisterInterface
{
    /**
     * @param EntityRepository<ActionButtonCollection> $actionButtonRepository
     */
    public function __construct(private readonly EntityRepository $actionButtonRepository)
    {
    }

    public function persist(AppLifecycleContext $context): void
    {
        $existingActionButtons = $this->getExistingActionButtons($context->app->getId(), $context->context);

        $actionButtons = $context->manifest->getAdmin() ? $context->manifest->getAdmin()->getActionButtons() : [];
        $upserts = [];
        foreach ($actionButtons as $actionButton) {
            $payload = $actionButton->toArray($context->defaultLocale);
            $payload['appId'] = $context->app->getId();

            $existing = $existingActionButtons->filterByProperty('action', $actionButton->getAction())->first();
            if ($existing) {
                $payload['id'] = $existing->getId();
                $existingActionButtons->remove($existing->getId());
            }

            $upserts[] = $payload;
        }

        if ($upserts !== []) {
            $this->actionButtonRepository->upsert($upserts, $context->context);
        }

        $this->deleteOldActions($existingActionButtons, $context->context);
    }

    private function deleteOldActions(ActionButtonCollection $toBeRemoved, Context $context): void
    {
        $ids = $toBeRemoved->getIds();

        if ($ids !== []) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));

            $this->actionButtonRepository->delete($ids, $context);
        }
    }

    private function getExistingActionButtons(string $appId, Context $context): ActionButtonCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->actionButtonRepository->search($criteria, $context)->getEntities();
    }
}
