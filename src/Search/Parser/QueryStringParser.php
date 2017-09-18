<?php

namespace Shopware\Search\Parser;

use Shopware\Search\Query\MatchQuery;
use Shopware\Search\Query\NestedQuery;
use Shopware\Search\Query\NotQuery;
use Shopware\Search\Query\Query;
use Shopware\Search\Query\RangeQuery;
use Shopware\Search\Query\TermQuery;
use Shopware\Search\Query\TermsQuery;

class QueryStringParser
{
    public function toUrl(Query $query): string
    {
        return json_encode(
            $this->toArray($query)
        );
    }

    public function fromUrl(string $url): Query
    {
        return $this->fromArray(
            json_decode($url, true)
        );
    }

    private function fromArray(array $query): Query
    {
        switch ($query['type']) {
            case 'term':
                return new TermQuery($query['field'], $query['value']);
            case 'nested':
                return new NestedQuery(
                    array_map([$this, 'fromArray'], $query['queries']),
                    $query['operator']
                );
            case 'match':
                return new MatchQuery($query['field'], $query['value']);
            case 'not':
                return new NotQuery(
                    array_map([$this, 'fromArray'], $query['queries']),
                    $query['operator']
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

    private function toArray(Query $query): array
    {
        switch (true) {
            case $query instanceof TermQuery:
                return [
                    'type' => 'term',
                    'field' => $query->getField(),
                    'value' => $query->getValue()
                ];
            case $query instanceof NestedQuery:
                return [
                    'type' => 'nested',
                    'queries' => array_map(function(Query $nested) {
                        return $this->toArray($nested);
                    }, $query->getQueries()),
                    'operator' => $query->getOperator()
                ];
            case $query instanceof MatchQuery:
                return [
                    'type' => 'match',
                    'field' => $query->getField(),
                    'value' => $query->getValue()
                ];
            case $query instanceof NotQuery:
                return [
                    'type' => 'not',
                    'queries' => array_map(function(Query $nested) {
                        return $this->toArray($nested);
                    }, $query->getQueries()),
                    'operator' => $query->getOperator()
                ];
            case $query instanceof RangeQuery:
                return [
                    'type' => 'range',
                    'field' => $query->getField(),
                    'parameters' => $query->getParameters()
                ];
            case $query instanceof TermsQuery:
                return [
                    'type' => 'term',
                    'field' => $query->getField(),
                    'value' => implode('|', $query->getValue())
                ];
            default:
                throw new \RuntimeException(sprintf('Unsupported query type %s', get_class($query)));
        }
    }

}