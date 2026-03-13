<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Aggregate\AppMcpPrompt\AppMcpPromptCollection;
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
class McpPromptPersister
{
    /**
     * @param EntityRepository<AppMcpPromptCollection> $mcpPromptRepository
     */
    public function __construct(
        private readonly EntityRepository $mcpPromptRepository,
    ) {
    }

    public function updatePrompts(?Mcp $mcp, string $appId, string $defaultLocale, Context $context): void
    {
        $existingPrompts = $this->getExistingPrompts($appId, $context);

        $prompts = $mcp?->getPrompts()?->getPrompts() ?? [];
        $upserts = [];

        foreach ($prompts as $prompt) {
            $payload = $prompt->toArray($defaultLocale);
            $payload['appId'] = $appId;

            $existing = $existingPrompts->filterByProperty('name', $prompt->getName())->first();
            if ($existing) {
                $payload['id'] = $existing->getId();
                $existingPrompts->remove($existing->getId());
            }

            $upserts[] = $payload;
        }

        if ($upserts !== []) {
            $this->mcpPromptRepository->upsert($upserts, $context);
        }

        $this->deleteRemovedPrompts($existingPrompts, $context);
    }

    private function deleteRemovedPrompts(AppMcpPromptCollection $toBeRemoved, Context $context): void
    {
        $ids = $toBeRemoved->getIds();

        if ($ids !== []) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));
            $this->mcpPromptRepository->delete($ids, $context);
        }
    }

    private function getExistingPrompts(string $appId, Context $context): AppMcpPromptCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->mcpPromptRepository->search($criteria, $context)->getEntities();
    }
}
