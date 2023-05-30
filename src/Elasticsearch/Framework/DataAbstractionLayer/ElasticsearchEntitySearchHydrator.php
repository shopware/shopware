<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('core')]
class ElasticsearchEntitySearchHydrator extends AbstractElasticsearchSearchHydrator
{
    public function getDecorated(): AbstractElasticsearchSearchHydrator
    {
        throw new DecorationPatternException(self::class);
    }

    public function hydrate(EntityDefinition $definition, Criteria $criteria, Context $context, array $result): IdSearchResult
    {
        if (!isset($result['hits'])) {
            return new IdSearchResult(0, [], $criteria, $context);
        }

        $hits = $this->extractHits($result);

        $data = [];
        foreach ($hits as $hit) {
            $id = $hit['_id'];

            $data[$id] = [
                'primaryKey' => $id,
                'data' => array_merge(
                    $hit['_source'] ?? [],
                    ['id' => $id, '_score' => $hit['_score']]
                ),
            ];
        }

        $total = $this->getTotalValue($criteria, $result);

        if ($criteria->useIdSorting()) {
            $data = $this->sortByIdArray($criteria->getIds(), $data);
        }

        return new IdSearchResult($total, $data, $criteria, $context);
    }

    private function extractHits(array $result): array
    {
        $records = [];
        $hits = $result['hits']['hits'];

        foreach ($hits as $hit) {
            if (!isset($hit['inner_hits'])) {
                $records[] = $hit;

                continue;
            }

            $nested = $this->extractHits($hit['inner_hits']['inner']);
            foreach ($nested as $inner) {
                $records[] = $inner;
            }
        }

        return $records;
    }

    private function getTotalValue(Criteria $criteria, array $result): int
    {
        if (!$criteria->getGroupFields()) {
            return (int) $result['hits']['total']['value'];
        }

        if (!$criteria->getPostFilters()) {
            return (int) $result['aggregations']['total-count']['value'];
        }

        return (int) $result['aggregations']['total-filtered-count']['total-count']['value'];
    }

    private function sortByIdArray(array $ids, array $data): array
    {
        $sorted = [];

        foreach ($ids as $id) {
            if (\is_array($id)) {
                $id = implode('-', $id);
            }

            if (\array_key_exists($id, $data)) {
                $sorted[$id] = $data[$id];
            }
        }

        return $sorted;
    }
}
