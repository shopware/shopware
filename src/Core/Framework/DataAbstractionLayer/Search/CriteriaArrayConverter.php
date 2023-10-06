<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class CriteriaArrayConverter
{
    /**
     * @internal
     */
    public function __construct(private readonly AggregationParser $aggregationParser)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function convert(Criteria $criteria): array
    {
        $array = [
            'total-count-mode' => $criteria->getTotalCountMode(),
        ];

        if ($criteria->getLimit()) {
            $array['limit'] = $criteria->getLimit();
        }

        if ($criteria->getOffset()) {
            $array['page'] = ($criteria->getOffset() / $criteria->getLimit()) + 1;
        }

        if ($criteria->getTerm()) {
            $array['term'] = $criteria->getTerm();
        }

        if ($criteria->getIncludes()) {
            $array['includes'] = $criteria->getIncludes();
        }

        if (\count($criteria->getIds())) {
            $array['ids'] = $criteria->getIds();
        }

        if (\count($criteria->getFilters())) {
            $array['filter'] = array_map(static fn (Filter $filter) => QueryStringParser::toArray($filter), $criteria->getFilters());
        }

        if (\count($criteria->getPostFilters())) {
            $array['post-filter'] = array_map(static fn (Filter $filter) => QueryStringParser::toArray($filter), $criteria->getPostFilters());
        }

        if (\count($criteria->getAssociations())) {
            foreach ($criteria->getAssociations() as $assocName => $association) {
                $array['associations'][$assocName] = $this->convert($association);
            }
        }

        if (\count($criteria->getSorting())) {
            $array['sort'] = json_decode(json_encode($criteria->getSorting(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

            foreach ($array['sort'] as &$sort) {
                $sort['order'] = $sort['direction'];
                unset($sort['direction']);
            }
            unset($sort);
        }

        if (\count($criteria->getQueries())) {
            $array['query'] = [];

            foreach ($criteria->getQueries() as $query) {
                $arrayQuery = [
                    'score' => $query->getScore(),
                    'scoreField' => $query->getScoreField(),
                    'extensions' => $query->getExtensions(),
                ];
                $arrayQuery['query'] = QueryStringParser::toArray($query->getQuery());
                $array['query'][] = $arrayQuery;
            }
        }

        if (\count($criteria->getGroupFields())) {
            $array['grouping'] = [];

            foreach ($criteria->getGroupFields() as $groupField) {
                $array['grouping'][] = $groupField->getField();
            }
        }

        if (\count($criteria->getAggregations())) {
            $array['aggregations'] = $this->aggregationParser->toArray($criteria->getAggregations());
        }

        return $array;
    }
}
