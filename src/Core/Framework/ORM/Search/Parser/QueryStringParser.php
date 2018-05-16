<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search\Parser;

use Shopware\Framework\ORM\Search\Query\MatchQuery;
use Shopware\Framework\ORM\Search\Query\NestedQuery;
use Shopware\Framework\ORM\Search\Query\NotQuery;
use Shopware\Framework\ORM\Search\Query\Query;
use Shopware\Framework\ORM\Search\Query\RangeQuery;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\Framework\ORM\Search\Query\TermsQuery;

class QueryStringParser
{
    public static function toUrl(Query $query): string
    {
        return json_encode(
            self::toArray($query)
        );
    }

    public static function fromUrl(string $url): Query
    {
        return self::fromArray(
            json_decode($url, true)
        );
    }

    public static function fromArray(array $query): Query
    {
        switch ($query['type']) {
            case 'term':
                return new TermQuery($query['field'], $query['value']);
            case 'nested':
                return new NestedQuery(
                    array_map(function (array $query) {
                        return self::fromArray($query);
                    }, $query['queries']),
                    array_key_exists('operator', $query) ? $query['operator'] : 'AND'
                );
            case 'match':
                return new MatchQuery($query['field'], $query['value']);
            case 'not':
                return new NotQuery(
                    array_map(function (array $query) {
                        return self::fromArray($query);
                    }, $query['queries']),
                    array_key_exists('operator', $query) ? $query['operator'] : 'AND'
                );
            case 'range':
                return new RangeQuery($query['field'], $query['parameters']);
            case 'terms':
                return new TermsQuery(
                    $query['field'],
                    array_filter(explode('|', $query['value']))
                );
            default:
                throw new \RuntimeException(sprintf('Unsupported query type %s', get_class($query)));
        }
    }

    private static function toArray(Query $query): array
    {
        switch (true) {
            case $query instanceof TermQuery:
                return [
                    'type' => 'term',
                    'field' => $query->getField(),
                    'value' => $query->getValue(),
                ];
            case $query instanceof NestedQuery:
                return [
                    'type' => 'nested',
                    'queries' => array_map(function (Query $nested) {
                        return self::toArray($nested);
                    }, $query->getQueries()),
                    'operator' => $query->getOperator(),
                ];
            case $query instanceof MatchQuery:
                return [
                    'type' => 'match',
                    'field' => $query->getField(),
                    'value' => $query->getValue(),
                ];
            case $query instanceof NotQuery:
                return [
                    'type' => 'not',
                    'queries' => array_map(function (Query $nested) {
                        return self::toArray($nested);
                    }, $query->getQueries()),
                    'operator' => $query->getOperator(),
                ];
            case $query instanceof RangeQuery:
                return [
                    'type' => 'range',
                    'field' => $query->getField(),
                    'parameters' => $query->getParameters(),
                ];
            case $query instanceof TermsQuery:
                return [
                    'type' => 'term',
                    'field' => $query->getField(),
                    'value' => implode('|', $query->getValue()),
                ];
            default:
                throw new \RuntimeException(sprintf('Unsupported query type %s', get_class($query)));
        }
    }
}
