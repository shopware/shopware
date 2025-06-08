<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class CriteriaArrayConverter
{
    /**
     * @internal
     */
    public function __construct(private readonly AggregationParser $aggregationParser)
    {
    }

    /**
     * You will see many `@var` annotations in this method. Please leave them for now.
     * Without them PHPStan needs 15s to analyze this file. This is because the array constructed here is very complex.
     * In order to not exclude the whole file from the analysis, this is a reasonable workaround.
     *
     * @return array<string, mixed>
     */
    public function convert(Criteria $criteria): array
    {
        /** @var array<string, mixed> $array */
        $array = [
            'total-count-mode' => $criteria->getTotalCountMode(),
        ];

        /** @var array<string, mixed> $array */
        if ($criteria->getLimit()) {
            $array['limit'] = $criteria->getLimit();
        }

        /** @var array<string, mixed> $array */
        if ($criteria->getOffset()) {
            $array['page'] = ($criteria->getOffset() / $criteria->getLimit()) + 1;
        }

        /** @var array<string, mixed> $array */
        if ($criteria->getTerm()) {
            $array['term'] = $criteria->getTerm();
        }

        /** @var array<string, mixed> $array */
        if ($criteria->getIncludes()) {
            $array['includes'] = $criteria->getIncludes();
        }

        /** @var array<string, mixed> $array */
        if (\count($criteria->getIds())) {
            $array['ids'] = $criteria->getIds();
        }

        /** @var array<string, mixed> $array */
        if (\count($criteria->getFilters())) {
            $array['filter'] = array_map(static fn (Filter $filter) => QueryStringParser::toArray($filter), $criteria->getFilters());
        }

        /** @var array<string, mixed> $array */
        if (\count($criteria->getPostFilters())) {
            $array['post-filter'] = array_map(static fn (Filter $filter) => QueryStringParser::toArray($filter), $criteria->getPostFilters());
        }

        /** @var array<string, mixed> $array */
        if (\count($criteria->getAssociations())) {
            foreach ($criteria->getAssociations() as $assocName => $association) {
                $array['associations'][$assocName] = $this->convert($association);
            }
        }

        /** @var array<string, mixed> $array */
        if (\count($criteria->getSorting())) {
            $array['sort'] = json_decode(json_encode($criteria->getSorting(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

            foreach ($array['sort'] as &$sort) {
                $sort['order'] = $sort['direction'];
                unset($sort['direction']);
            }
            unset($sort);
        }

        /** @var array<string, mixed> $array */
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

        /** @var array<string, mixed> $array */
        if (\count($criteria->getGroupFields())) {
            $array['grouping'] = [];

            foreach ($criteria->getGroupFields() as $groupField) {
                $array['grouping'][] = $groupField->getField();
            }
        }

        /** @var array<string, mixed> $array */
        if (\count($criteria->getAggregations())) {
            $array['aggregations'] = $this->aggregationParser->toArray($criteria->getAggregations());
        }

        return $array;
    }
}
