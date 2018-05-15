<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search\Parser;

use Doctrine\DBAL\Connection;
use Shopware\Framework\ORM\Dbal\EntityDefinitionQueryHelper;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\JsonArrayField;
use Shopware\Framework\ORM\Search\Query\MatchQuery;
use Shopware\Framework\ORM\Search\Query\NestedQuery;
use Shopware\Framework\ORM\Search\Query\NotQuery;
use Shopware\Framework\ORM\Search\Query\Query;
use Shopware\Framework\ORM\Search\Query\RangeQuery;
use Shopware\Framework\ORM\Search\Query\ScoreQuery;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\Framework\ORM\Search\Query\TermsQuery;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Struct\Uuid;

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

    public function parseRanking(array $queries, string $definition, string $root, ApplicationContext $context): ParseResult
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

    public function parse(Query $query, string $definition, ApplicationContext $context, string $root = null): ParseResult
    {
        if ($root === null) {
            /** @var EntityDefinition $definition */
            $root = $definition::getEntityName();
        }

        switch (true) {
            case $query instanceof NotQuery:
                return $this->parseNotQuery($query, $definition, $root, $context);
            case $query instanceof NestedQuery:
                return $this->parseNestedQuery($query, $definition, $root, $context);
            case $query instanceof TermQuery:
                return $this->parseTermQuery($query, $definition, $root, $context);
            case $query instanceof TermsQuery:
                return $this->parseTermsQuery($query, $definition, $root, $context);
            case $query instanceof MatchQuery:
                return $this->parseMatchQuery($query, $definition, $root, $context);
            case $query instanceof RangeQuery:
                return $this->parseRangeQuery($query, $definition, $root, $context);
            default:
                throw new \RuntimeException(sprintf('Unsupported query %s', get_class($query)));
        }
    }

    private function parseRangeQuery(RangeQuery $query, string $definition, string $root, ApplicationContext $context): ParseResult
    {
        $result = new ParseResult();

        $key = $this->getKey();

        $field = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context);

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

    private function parseMatchQuery(MatchQuery $query, string $definition, string $root, ApplicationContext $context): ParseResult
    {
        $key = $this->getKey();

        $field = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context);

        $result = new ParseResult();
        $result->addWhere($field . ' LIKE :' . $key);
        $result->addParameter($key, '%' . $query->getValue() . '%');

        return $result;
    }

    private function parseTermsQuery(TermsQuery $query, string $definition, string $root, ApplicationContext $context): ParseResult
    {
        $key = $this->getKey();
        $select = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context);
        $field = $this->queryHelper->getField($query->getField(), $definition, $root);

        $result = new ParseResult();

        if ($field instanceof JsonArrayField) {
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
                return Uuid::fromStringToBytes($id);
            }, $value);
        }
        $result->addParameter($key, $value, Connection::PARAM_STR_ARRAY);

        return $result;
    }

    private function parseTermQuery(TermQuery $query, string $definition, string $root, ApplicationContext $context): ParseResult
    {
        $key = $this->getKey();
        $select = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context);
        $field = $this->queryHelper->getField($query->getField(), $definition, $root);

        $result = new ParseResult();

        if ($field instanceof JsonArrayField) {
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
            $value = Uuid::fromStringToBytes($value);
        }

        $result->addParameter($key, $value);

        return $result;
    }

    private function parseNestedQuery(NestedQuery $query, string $definition, string $root, ApplicationContext $context): ParseResult
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

    private function parseNotQuery(NotQuery $query, string $definition, string $root, ApplicationContext $context): ParseResult
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

    private function iterateNested(NestedQuery $query, string $definition, string $root, ApplicationContext $context): ParseResult
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
        return 'param_' . str_replace('-', '', Uuid::uuid4()->getHex());
    }
}
