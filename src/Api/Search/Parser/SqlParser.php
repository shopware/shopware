<?php declare(strict_types=1);

namespace Shopware\Api\Search\Parser;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Search\Query\MatchQuery;
use Shopware\Api\Search\Query\NestedQuery;
use Shopware\Api\Search\Query\NotQuery;
use Shopware\Api\Search\Query\Query;
use Shopware\Api\Search\Query\RangeQuery;
use Shopware\Api\Search\Query\TermQuery;
use Shopware\Api\Search\Query\TermsQuery;
use Shopware\Api\Search\QuerySelection;

class SqlParser
{
    public function parse(Query $query, QuerySelection $selection): ParseResult
    {
        switch (true) {
            case $query instanceof NotQuery:
                return $this->parseNotQuery($query, $selection);

            case $query instanceof NestedQuery:
                return $this->parseNestedQuery($query, $selection);

            case $query instanceof TermQuery:
                return $this->parseTermQuery($query, $selection);

            case $query instanceof TermsQuery:
                return $this->parseTermsQuery($query, $selection);

            case $query instanceof MatchQuery:
                return $this->parseMatchQuery($query, $selection);

            case $query instanceof RangeQuery:
                return $this->parseRangeQuery($query, $selection);

            default:
                throw new \RuntimeException(sprintf('Unsupported query %s', get_class($query)));
        }
    }

    private function parseRangeQuery(RangeQuery $query, QuerySelection $selection): ParseResult
    {
        $result = new ParseResult();

        $key = $this->getKey();

        $field = $selection->getFieldEscaped($query->getField());

        $where = [];

        if ($query->hasParameter(RangeQuery::GT)) {
            $where[] = $field . ' > :' . $key;
            $result->addParameter($key, $query->getParameter(RangeQuery::GT));
        } elseif ($query->hasParameter(RangeQuery::GTE)) {
            $where[] = $field . ' >= :' . $key;
            $result->addParameter($key, $query->getParameter(RangeQuery::GTE));
        }

        $key = $this->getKey();

        if ($query->hasParameter(RangeQuery::LT)) {
            $where[] = $field . ' < :' . $key;
            $result->addParameter($key, $query->getParameter(RangeQuery::LT));
        } elseif ($query->hasParameter(RangeQuery::LTE)) {
            $where[] = $field . ' <= :' . $key;
            $result->addParameter($key, $query->getParameter(RangeQuery::LTE));
        }

        $where = '(' . implode(' AND ', $where) . ')';
        $result->addWhere($where);

        return $result;
    }

    private function parseMatchQuery(MatchQuery $query, QuerySelection $selection): ParseResult
    {
        $key = $this->getKey();
        $field = $selection->getFieldEscaped($query->getField());

        $result = new ParseResult();
        $result->addWhere($field . ' LIKE :' . $key);
        $result->addParameter($key, '%' . $query->getValue() . '%');

        return $result;
    }

    private function parseTermsQuery(TermsQuery $query, QuerySelection $selection): ParseResult
    {
        $key = $this->getKey();
        $field = $selection->getFieldEscaped($query->getField());

        $result = new ParseResult();
        $result->addWhere($field . ' IN (:' . $key . ')');
        $result->addParameter($key, $query->getValue(), Connection::PARAM_STR_ARRAY);

        return $result;
    }

    private function parseTermQuery(TermQuery $query, QuerySelection $selection): ParseResult
    {
        $key = $this->getKey();
        $field = $selection->getFieldEscaped($query->getField());

        $result = new ParseResult();
        $result->addWhere($field . ' = :' . $key);
        $result->addParameter($key, $query->getValue());

        return $result;
    }

    private function parseNestedQuery(NestedQuery $query, QuerySelection $selection): ParseResult
    {
        $result = $this->iterateNested($query, $selection);

        $wheres = $result->getWheres();

        $result->resetWheres();

        $glue = ' ' . $query->getOperator() . ' ';
        if (!empty($wheres)) {
            $result->addWhere('(' . implode($glue, $wheres) . ')');
        }

        return $result;
    }

    private function parseNotQuery(NotQuery $query, QuerySelection $selection): ParseResult
    {
        $result = $this->iterateNested($query, $selection);

        $wheres = $result->getWheres();

        $result->resetWheres();

        $glue = ' ' . $query->getOperator() . ' ';
        if (!empty($wheres)) {
            $result->addWhere('NOT (' . implode($glue, $wheres) . ')');
        }

        return $result;
    }

    private function getKey(): string
    {
        return 'param_' . str_replace('-', '', Uuid::uuid4()->toString());
    }

    private function iterateNested(NestedQuery $query, QuerySelection $selection): ParseResult
    {
        $result = new ParseResult();
        foreach ($query->getQueries() as $nestedQuery) {
            $result = $result->merge(
                $this->parse($nestedQuery, $selection)
            );
        }

        return $result;
    }
}
