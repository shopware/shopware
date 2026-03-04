<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolCollection;
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
class McpToolPersister
{
    /**
     * @param EntityRepository<AppMcpToolCollection> $mcpToolRepository
     */
    public function __construct(
        private readonly EntityRepository $mcpToolRepository,
    ) {
    }

    public function updateTools(?Mcp $mcp, string $appId, string $defaultLocale, Context $context): void
    {
        $existingTools = $this->getExistingTools($appId, $context);

        $tools = $mcp?->getTools()?->getTools() ?? [];
        $upserts = [];

        foreach ($tools as $tool) {
            $payload = $tool->toArray($defaultLocale);
            $payload['appId'] = $appId;

            $existing = $existingTools->filterByProperty('name', $tool->getName())->first();
            if ($existing) {
                $payload['id'] = $existing->getId();
                $existingTools->remove($existing->getId());
            }

            $upserts[] = $payload;
        }

        if ($upserts !== []) {
            $this->mcpToolRepository->upsert($upserts, $context);
        }

        $this->deleteRemovedTools($existingTools, $context);
    }

    private function deleteRemovedTools(AppMcpToolCollection $toBeRemoved, Context $context): void
    {
        $ids = $toBeRemoved->getIds();

        if ($ids !== []) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));
            $this->mcpToolRepository->delete($ids, $context);
        }
    }

    private function getExistingTools(string $appId, Context $context): AppMcpToolCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->mcpToolRepository->search($criteria, $context)->getEntities();
    }
}
