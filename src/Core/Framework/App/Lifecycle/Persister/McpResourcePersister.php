<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Aggregate\AppMcpResource\AppMcpResourceCollection;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends AbstractMcpCapabilityPersister<AppMcpResourceCollection>
 */
#[Package('framework')]
class McpResourcePersister extends AbstractMcpCapabilityPersister
{
    /**
     * @param EntityRepository<AppMcpResourceCollection> $mcpResourceRepository
     */
    public function __construct(
        private readonly EntityRepository $mcpResourceRepository,
    ) {
    }

    protected function getItemsFromMcp(?Mcp $mcp): array
    {
        return $mcp?->getResources()?->getResources() ?? [];
    }

    /**
     * @return EntityRepository<AppMcpResourceCollection>
     */
    protected function getRepository(): EntityRepository
    {
        return $this->mcpResourceRepository;
    }

    /**
     * @return AppMcpResourceCollection
     */
    protected function fetchExisting(string $appId, Context $context): EntityCollection
    {
        return $this->searchByAppId($this->mcpResourceRepository, $appId, $context);
    }
}
