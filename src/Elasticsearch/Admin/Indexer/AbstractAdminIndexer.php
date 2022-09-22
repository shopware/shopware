<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use ONGR\ElasticsearchDSL\Search;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 */
abstract class AbstractAdminIndexer
{
    abstract public function getDecorated(): self;

    abstract public function getName(): string;

    abstract public function getEntity(): string;

    /**
     * @param array<string, array<string, array<string, string>>> $mapping
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public function mapping(array $mapping): array
    {
        return $mapping;
    }

    final public function getIndex(): string
    {
        return EnvironmentHelper::getVariable('SHOPWARE_ADMIN_ES_INDEX_PREFIX', 'sw-admin') . '-' . \strtolower(\str_replace(['_', ' '], '-', $this->getName()));
    }

    abstract public function getIterator(): IterableQuery;

    /**
     * @param array<string>|array<int, array<string>> $ids
     *
     * @return array<string, array<string, string>>
     */
    abstract public function fetch(array $ids): array;

    /**
     * @param array<string, mixed> $result
     *
     * @return array{total:int, data:EntityCollection<Entity>}
     */
    abstract public function globalData(array $result, Context $context): array;

    public function globalCriteria(string $term, Search $criteria): Search
    {
        return $criteria;
    }
}
