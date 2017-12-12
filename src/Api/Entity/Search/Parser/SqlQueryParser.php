<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search\Parser;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Dbal\EntityDefinitionResolver;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Search\Query\MatchQuery;
use Shopware\Api\Entity\Search\Query\NestedQuery;
use Shopware\Api\Entity\Search\Query\NotQuery;
use Shopware\Api\Entity\Search\Query\Query;
use Shopware\Api\Entity\Search\Query\RangeQuery;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Entity\Search\Query\ScoreQuery;

class SqlQueryParser
{
    public static function parseRanking(array $queries, string $definition, string $root)
    {
        $result = new ParseResult();

        /** @var ScoreQuery $query */
        foreach ($queries as $query) {
            $parsed = self::parse($query->getQuery(), $definition, $root);

            foreach ($parsed->getWheres() as $where) {
                $result->addWhere(
                    sprintf('IF(%s , %s, 0)', $where, $query->getScore())
                );
            }

            foreach ($parsed->getParameters() as $key => $parameter) {
                $result->addParameter($key, $parameter, $parsed->getType($key));
            }
        }

        return $result;
    }

    public static function parse(Query $query, string $definition, string $root = null): ParseResult
    {
        if ($root === null) {
            /** @var EntityDefinition $definition */
            $root = $definition::getEntityName();
        }

        switch (true) {
            case $query instanceof NotQuery:
                return self::parseNotQuery($query, $definition, $root);
            case $query instanceof NestedQuery:
                return self::parseNestedQuery($query, $definition, $root);
            case $query instanceof TermQuery:
                return self::parseTermQuery($query, $definition, $root);
            case $query instanceof TermsQuery:
                return self::parseTermsQuery($query, $definition, $root);
            case $query instanceof MatchQuery:
                return self::parseMatchQuery($query, $definition, $root);
            case $query instanceof RangeQuery:
                return self::parseRangeQuery($query, $definition, $root);
            default:
                throw new \RuntimeException(sprintf('Unsupported query %s', get_class($query)));
        }
    }

    private static function parseRangeQuery(RangeQuery $query, string $definition, string $root): ParseResult
    {
        $result = new ParseResult();

        $key = self::getKey();

        $field = EntityDefinitionResolver::resolveField($query->getField(), $definition, $root);

        $where = [];

        if ($query->hasParameter(RangeQuery::GT)) {
            $where[] = $field . ' > :' . $key;
            $result->addParameter($key, $query->getParameter(RangeQuery::GT));
        } elseif ($query->hasParameter(RangeQuery::GTE)) {
            $where[] = $field . ' >= :' . $key;
            $result->addParameter($key, $query->getParameter(RangeQuery::GTE));
        }

        $key = self::getKey();

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

    private static function parseMatchQuery(MatchQuery $query, string $definition, string $root): ParseResult
    {
        $key = self::getKey();

        $field = EntityDefinitionResolver::resolveField($query->getField(), $definition, $root);

        $result = new ParseResult();
        $result->addWhere($field . ' LIKE :' . $key);
        $result->addParameter($key, '%' . $query->getValue() . '%');

        return $result;
    }

    private static function parseTermsQuery(TermsQuery $query, string $definition, string $root): ParseResult
    {
        $key = self::getKey();
        $field = EntityDefinitionResolver::resolveField($query->getField(), $definition, $root);

        $result = new ParseResult();
        $result->addWhere($field . ' IN (:' . $key . ')');
        $result->addParameter($key, array_values($query->getValue()), Connection::PARAM_STR_ARRAY);

        return $result;
    }

    private static function parseTermQuery(TermQuery $query, string $definition, string $root): ParseResult
    {
        $key = self::getKey();
        $field = EntityDefinitionResolver::resolveField($query->getField(), $definition, $root);

        $result = new ParseResult();
        if ($query->getValue() === null) {
            $result->addWhere($field . ' IS NULL');

            return $result;
        }

        $result->addWhere($field . ' = :' . $key);
        $result->addParameter($key, $query->getValue());

        return $result;
    }

    private static function parseNestedQuery(NestedQuery $query, string $definition, string $root): ParseResult
    {
        $result = self::iterateNested($query, $definition, $root);

        $wheres = $result->getWheres();

        $result->resetWheres();

        $glue = ' ' . $query->getOperator() . ' ';
        if (!empty($wheres)) {
            $result->addWhere('(' . implode($glue, $wheres) . ')');
        }

        return $result;
    }

    private static function parseNotQuery(NotQuery $query, string $definition, string $root): ParseResult
    {
        $result = self::iterateNested($query, $definition, $root);

        $wheres = $result->getWheres();

        $result->resetWheres();

        $glue = ' ' . $query->getOperator() . ' ';
        if (!empty($wheres)) {
            $result->addWhere('NOT (' . implode($glue, $wheres) . ')');
        }

        return $result;
    }

    private static function iterateNested(NestedQuery $query, string $definition, string $root): ParseResult
    {
        $result = new ParseResult();
        foreach ($query->getQueries() as $nestedQuery) {
            $result = $result->merge(
                self::parse($nestedQuery, $definition, $root)
            );
        }

        return $result;
    }

    private static function getKey(): string
    {
        return 'param_' . str_replace('-', '', Uuid::uuid4()->toString());
    }
}
