<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Parser;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\MatchQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\NestedQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\Query;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\TermQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\TermsQuery;
use Shopware\Core\Framework\Struct\Uuid;

class SqlQueryParser
{
    /**
     * @var EntityDefinitionQueryHelper
     */
    private $queryHelper;

    public function __construct(EntityDefinitionQueryHelper $queryHelper)
    {
        $this->queryHelper = $queryHelper;
    }

    public function parseRanking(array $queries, string $definition, string $root, Context $context): ParseResult
    {
        $result = new ParseResult();

        /** @var ScoreQuery $query */
        foreach ($queries as $query) {
            $parsed = $this->parse($query->getQuery(), $definition, $context, $root);

            foreach ($parsed->getWheres() as $where) {
                if ($query->getScoreField()) {
                    $field = $this->queryHelper->getFieldAccessor(
                        $query->getScoreField(),
                        $definition,
                        $root,
                        $context
                    );

                    $result->addWhere(
                        sprintf('IF(%s , %s * %s, 0)', $where, $query->getScore(), $field)
                    );
                    continue;
                }

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

    public function parse(Query $query, string $definition, Context $context, string $root = null): ParseResult
    {
        if ($root === null) {
            /** @var EntityDefinition $definition */
            $root = $definition::getEntityName();
        }

        switch (true) {
            case $query instanceof NotFilter:
                return $this->parseNotFilter($query, $definition, $root, $context);
            case $query instanceof NestedQuery:
                return $this->parseNestedQuery($query, $definition, $root, $context);
            case $query instanceof TermQuery:
                return $this->parseTermQuery($query, $definition, $root, $context);
            case $query instanceof TermsQuery:
                return $this->parseTermsQuery($query, $definition, $root, $context);
            case $query instanceof MatchQuery:
                return $this->parseMatchQuery($query, $definition, $root, $context);
            case $query instanceof RangeFilter:
                return $this->parseRangeFilter($query, $definition, $root, $context);
            default:
                throw new \RuntimeException(sprintf('Unsupported query %s', \get_class($query)));
        }
    }

    private function parseRangeFilter(RangeFilter $query, string $definition, string $root, Context $context): ParseResult
    {
        $result = new ParseResult();

        $key = $this->getKey();

        $field = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context);

        $where = [];

        if ($query->hasParameter(RangeFilter::GT)) {
            $where[] = $field . ' > :' . $key;
            $result->addParameter($key, $query->getParameter(RangeFilter::GT));
        } elseif ($query->hasParameter(RangeFilter::GTE)) {
            $where[] = $field . ' >= :' . $key;
            $result->addParameter($key, $query->getParameter(RangeFilter::GTE));
        }

        $key = $this->getKey();

        if ($query->hasParameter(RangeFilter::LT)) {
            $where[] = $field . ' < :' . $key;
            $result->addParameter($key, $query->getParameter(RangeFilter::LT));
        } elseif ($query->hasParameter(RangeFilter::LTE)) {
            $where[] = $field . ' <= :' . $key;
            $result->addParameter($key, $query->getParameter(RangeFilter::LTE));
        }

        $where = '(' . implode(' AND ', $where) . ')';
        $result->addWhere($where);

        return $result;
    }

    private function parseMatchQuery(MatchQuery $query, string $definition, string $root, Context $context): ParseResult
    {
        $key = $this->getKey();

        $field = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context);

        $result = new ParseResult();
        $result->addWhere($field . ' LIKE :' . $key);
        $result->addParameter($key, '%' . $query->getValue() . '%');

        return $result;
    }

    private function parseTermsQuery(TermsQuery $query, string $definition, string $root, Context $context): ParseResult
    {
        $key = $this->getKey();
        $select = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context);
        $field = $this->queryHelper->getField($query->getField(), $definition, $root);

        $result = new ParseResult();

        if ($field instanceof ListField) {
            $result->addWhere('JSON_CONTAINS(' . $select . ', JSON_ARRAY(:' . $key . '))');

            if (\is_array($query->getValue())) {
                $result->addParameter($key, $query->getValue(), Connection::PARAM_STR_ARRAY);

                return $result;
            }
            $result->addParameter($key, $query->getValue());

            return $result;
        }

        $result->addWhere($select . ' IN (:' . $key . ')');

        $value = array_values($query->getValue());
        if ($field instanceof IdField || $field instanceof FkField) {
            $value = array_map(function (string $id) {
                return Uuid::fromHexToBytes($id);
            }, $value);
        }
        $result->addParameter($key, $value, Connection::PARAM_STR_ARRAY);

        return $result;
    }

    private function parseTermQuery(TermQuery $query, string $definition, string $root, Context $context): ParseResult
    {
        $key = $this->getKey();
        $select = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context);
        $field = $this->queryHelper->getField($query->getField(), $definition, $root);

        $result = new ParseResult();

        if ($field instanceof ListField) {
            $result->addWhere('JSON_CONTAINS(' . $select . ', JSON_ARRAY(:' . $key . '))');
            $result->addParameter($key, $query->getValue());

            return $result;
        }

        if ($query->getValue() === null) {
            $result->addWhere($select . ' IS NULL');

            return $result;
        }
        $result->addWhere($select . ' = :' . $key);

        $value = $query->getValue();
        if ($field instanceof IdField || $field instanceof FkField) {
            $value = Uuid::fromHexToBytes($value);
        }

        $result->addParameter($key, $value);

        return $result;
    }

    private function parseNestedQuery(NestedQuery $query, string $definition, string $root, Context $context): ParseResult
    {
        $result = $this->iterateNested($query, $definition, $root, $context);

        $wheres = $result->getWheres();

        $result->resetWheres();

        $glue = ' ' . $query->getOperator() . ' ';
        if (!empty($wheres)) {
            $result->addWhere('(' . implode($glue, $wheres) . ')');
        }

        return $result;
    }

    private function parseNotFilter(NotFilter $query, string $definition, string $root, Context $context): ParseResult
    {
        $result = $this->iterateNested($query, $definition, $root, $context);

        $wheres = $result->getWheres();

        $result->resetWheres();

        $glue = ' ' . $query->getOperator() . ' ';
        if (!empty($wheres)) {
            $result->addWhere('NOT (' . implode($glue, $wheres) . ')');
        }

        return $result;
    }

    private function iterateNested(NestedQuery $query, string $definition, string $root, Context $context): ParseResult
    {
        $result = new ParseResult();
        foreach ($query->getQueries() as $nestedQuery) {
            $result = $result->merge(
                $this->parse($nestedQuery, $definition, $context, $root)
            );
        }

        return $result;
    }

    private function getKey(): string
    {
        return 'param_' . Uuid::uuid4()->getHex();
    }
}
