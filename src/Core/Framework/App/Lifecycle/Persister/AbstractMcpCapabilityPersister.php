<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpCapabilityItem;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @template TEntityCollection of EntityCollection
 */
#[Package('framework')]
abstract class AbstractMcpCapabilityPersister
{
    public function persist(?Mcp $mcp, string $appId, string $defaultLocale, Context $context): void
    {
        $existing = $this->fetchExisting($appId, $context);
        $upserts = [];

        foreach ($this->getItemsFromMcp($mcp) as $item) {
            $payload = $item->toArray($defaultLocale);
            $payload['appId'] = $appId;

            $match = $existing->filterByProperty('name', $item->getName())->first();
            if ($match !== null) {
                $payload['id'] = $match->getUniqueIdentifier();
                $existing->remove($match->getUniqueIdentifier());
            }

            $upserts[] = $payload;
        }

        if ($upserts !== []) {
            $this->getRepository()->upsert($upserts, $context);
        }

        $this->deleteRemoved($existing, $context);
    }

    /**
     * @return list<McpCapabilityItem>
     */
    abstract protected function getItemsFromMcp(?Mcp $mcp): array;

    /**
     * @return EntityRepository<TEntityCollection>
     */
    abstract protected function getRepository(): EntityRepository;

    /**
     * @return TEntityCollection
     */
    abstract protected function fetchExisting(string $appId, Context $context): EntityCollection;

    /**
     * @param EntityRepository<TEntityCollection> $repository
     *
     * @return TEntityCollection
     */
    protected function searchByAppId(EntityRepository $repository, string $appId, Context $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $repository->search($criteria, $context)->getEntities();
    }

    /**
     * @param EntityCollection<Entity> $toBeRemoved
     */
    private function deleteRemoved(EntityCollection $toBeRemoved, Context $context): void
    {
        $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($toBeRemoved->getIds()));

        if ($ids !== []) {
            $this->getRepository()->delete($ids, $context);
        }
    }
}
