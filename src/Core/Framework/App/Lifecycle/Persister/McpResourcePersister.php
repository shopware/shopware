<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Aggregate\AppMcpResource\AppMcpResourceCollection;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class McpResourcePersister
{
    /**
     * @param EntityRepository<AppMcpResourceCollection> $mcpResourceRepository
     */
    public function __construct(
        private readonly EntityRepository $mcpResourceRepository,
    ) {
    }

    public function updateResources(?Mcp $mcp, string $appId, string $defaultLocale, Context $context): void
    {
        $existingResources = $this->getExistingResources($appId, $context);

        $resources = $mcp?->getResources()?->getResources() ?? [];
        $upserts = [];

        foreach ($resources as $resource) {
            $payload = $resource->toArray($defaultLocale);
            $payload['appId'] = $appId;

            $existing = $existingResources->filterByProperty('name', $resource->getName())->first();
            if ($existing) {
                $payload['id'] = $existing->getId();
                $existingResources->remove($existing->getId());
            }

            $upserts[] = $payload;
        }

        if ($upserts !== []) {
            $this->mcpResourceRepository->upsert($upserts, $context);
        }

        $this->deleteRemovedResources($existingResources, $context);
    }

    private function deleteRemovedResources(AppMcpResourceCollection $toBeRemoved, Context $context): void
    {
        $ids = $toBeRemoved->getIds();

        if ($ids !== []) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));
            $this->mcpResourceRepository->delete($ids, $context);
        }
    }

    private function getExistingResources(string $appId, Context $context): AppMcpResourceCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->mcpResourceRepository->search($criteria, $context)->getEntities();
    }
}
