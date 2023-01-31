<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonCollection;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class ActionButtonPersister
{
    public function __construct(private readonly EntityRepository $actionButtonRepository)
    {
    }

    public function updateActions(Manifest $manifest, string $appId, string $defaultLocale, Context $context): void
    {
        $existingActionButtons = $this->getExistingActionButtons($appId, $context);

        $actionButtons = $manifest->getAdmin() ? $manifest->getAdmin()->getActionButtons() : [];
        $upserts = [];
        foreach ($actionButtons as $actionButton) {
            $payload = $actionButton->toArray($defaultLocale);
            $payload['appId'] = $appId;

            /** @var ActionButtonEntity|null $existing */
            $existing = $existingActionButtons->filterByProperty('action', $actionButton->getAction())->first();
            if ($existing) {
                $payload['id'] = $existing->getId();
                $existingActionButtons->remove($existing->getId());
            }

            $upserts[] = $payload;
        }

        if (!empty($upserts)) {
            $this->actionButtonRepository->upsert($upserts, $context);
        }

        $this->deleteOldActions($existingActionButtons, $context);
    }

    private function deleteOldActions(ActionButtonCollection $toBeRemoved, Context $context): void
    {
        /** @var array<string> $ids */
        $ids = $toBeRemoved->getIds();

        if (!empty($ids)) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));

            $this->actionButtonRepository->delete($ids, $context);
        }
    }

    private function getExistingActionButtons(string $appId, Context $context): ActionButtonCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        /** @var ActionButtonCollection $actionButtons */
        $actionButtons = $this->actionButtonRepository->search($criteria, $context)->getEntities();

        return $actionButtons;
    }
}
