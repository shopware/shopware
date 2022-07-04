<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\Common\Collections\Criteria;
use ONGR\ElasticsearchDSL\Search;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\Struct\Struct;

abstract class AdminSearchIndexer
{
    abstract public function getDecorated(): self;

    abstract public function getName(): string;

    abstract public function getEntity(): string;

    public function mapping(array $mapping): array
    {
        return $mapping;
    }

    final public function getIndex(): string
    {
        return 'admin-' . \strtolower(\str_replace(['_', ' '], '-', $this->getName()));
    }

    abstract public function getIterator(): IterableQuery;

    /**
     * @param array $ids<string>
     * @return array<{id:string, text:string}>
     */
    abstract public function fetch(array $ids): array;

    /**
     * @param array $result{index:string, total:int, hits:array<{id:string, score:float, parameters:array, entity_name:string}>}
     * @param Context $context
     * @return array{total:int, data:array<Struct>}
     */
    abstract public function globalData(array $result, Context $context): array;

    public function globalCriteria(string $term, Search $criteria): Search
    {
        return $criteria;
    }
}
