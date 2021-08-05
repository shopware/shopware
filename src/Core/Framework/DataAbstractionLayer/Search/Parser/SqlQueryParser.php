<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Parser;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SingleFieldFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\Uuid\Uuid;

class SqlQueryParser
{
    /**
     * @var EntityDefinitionQueryHelper
     */
    private $queryHelper;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(EntityDefinitionQueryHelper $queryHelper, Connection $connection)
    {
        $this->queryHelper = $queryHelper;
        $this->connection = $connection;
    }

    public function parseRanking(
        array $queries,
        EntityDefinition $definition,
        string $root,
        Context $context
    ): ParseResult {
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
                        sprintf('IF(%s , %s * %s, 0)', $where, $this->connection->quote($query->getScore()), $field)
                    );

                    continue;
                }

                $result->addWhere(
                    sprintf('IF(%s , %s, 0)', $where, $this->connection->quote($query->getScore()))
                );
            }

            foreach ($parsed->getParameters() as $key => $parameter) {
                $result->addParameter($key, $parameter, $parsed->getType($key));
            }
        }

        return $result;
    }

    public function parse(
        Filter $query,
        EntityDefinition $definition,
        Context $context,
        ?string $root = null,
        bool $negated = false
    ): ParseResult {
        if ($root === null) {
            $root = $definition->getEntityName();
        }

        if ($query instanceof SingleFieldFilter && $query->getResolved()) {
            $result = new ParseResult();
            $result->addWhere((string) $query->getResolved());

            return $result;
        }

        switch (true) {
            case $query instanceof EqualsFilter:
                return $this->parseEqualsFilter($query, $definition, $root, $context, $negated);
            case $query instanceof EqualsAnyFilter:
                return $this->parseEqualsAnyFilter($query, $definition, $root, $context);
            case $query instanceof ContainsFilter:
                return $this->parseContainsFilter($query, $definition, $root, $context);
            case $query instanceof PrefixFilter:
                return $this->parsePrefixFilter($query, $definition, $root, $context);
            case $query instanceof SuffixFilter:
                return $this->parseSuffixFilter($query, $definition, $root, $context);
            case $query instanceof RangeFilter:
                return $this->parseRangeFilter($query, $definition, $root, $context);
            case $query instanceof NotFilter:
                return $this->parseNotFilter($query, $definition, $root, $context);
            case $query instanceof MultiFilter:
                return $this->parseMultiFilter($query, $definition, $root, $context, $negated);
            default:
                throw new \RuntimeException(sprintf('Unsupported query %s', \get_class($query)));
        }
    }

    private function parseRangeFilter(
        RangeFilter $query,
        EntityDefinition $definition,
        string $root,
        Context $context
    ): ParseResult {
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

    private function parseContainsFilter(ContainsFilter $query, EntityDefinition $definition, string $root, Context $context): ParseResult
    {
        $key = $this->getKey();

        $field = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context);

        $result = new ParseResult();
        $result->addWhere($field . ' LIKE :' . $key);

        $escaped = addcslashes($query->getValue(), '\\_%');
        $result->addParameter($key, '%' . $escaped . '%');

        return $result;
    }

    private function parsePrefixFilter(PrefixFilter $query, EntityDefinition $definition, string $root, Context $context): ParseResult
    {
        $key = $this->getKey();

        $field = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context);

        $result = new ParseResult();
        $result->addWhere($field . ' LIKE :' . $key);

        $escaped = addcslashes($query->getValue(), '\\_%');
        $result->addParameter($key, $escaped . '%');

        return $result;
    }

    private function parseSuffixFilter(SuffixFilter $query, EntityDefinition $definition, string $root, Context $context): ParseResult
    {
        $key = $this->getKey();

        $field = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context);

        $result = new ParseResult();
        $result->addWhere($field . ' LIKE :' . $key);

        $escaped = addcslashes($query->getValue(), '\\_%');
        $result->addParameter($key, '%' . $escaped);

        return $result;
    }

    private function parseEqualsAnyFilter(EqualsAnyFilter $query, EntityDefinition $definition, string $root, Context $context): ParseResult
    {
        $key = $this->getKey();
        $select = $this->queryHelper->getFieldAccessor($query->getField(), $definition, $root, $context);
        $field = $this->queryHelper->getField($query->getField(), $definition, $root);

        $result = new ParseResult();

        if ($field instanceof ListField) {
            if (\is_array($query->getValue())) {
                $where = [];

                foreach ($query->getValue() as $value) {
                    $key = $this->getKey();
                    $where[] = sprintf('JSON_CONTAINS(%s, JSON_ARRAY(%s))', $select, ':' . $key);
                    $result->addParameter($key, $value);
                }
                $result->addWhere('(' . implode(' OR ', $where) . ')');

                return $result;
            }

            $result->addWhere('JSON_CONTAINS(' . $select . ', JSON_ARRAY(:' . $key . '))');
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

    private function parseEqualsFilter(EqualsFilter $query, EntityDefinition $definition, string $root, Context $context, bool $negated): ParseResult
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

        if ($negated || $query->getValue() === null) {
            $result->addWhere($select . ' <=> :' . $key);
        } else {
            $result->addWhere($select . ' = :' . $key);
        }

        $value = $query->getValue();
        if ($value === null) {
            $result->addParameter($key, null);

            return $result;
        }

        if ($field instanceof IdField || $field instanceof FkField) {
            $value = Uuid::fromHexToBytes($value);
        }

        $result->addParameter($key, $value);

        return $result;
    }

    private function parseMultiFilter(MultiFilter $query, EntityDefinition $definition, string $root, Context $context, bool $negated): ParseResult
    {
        $result = $this->iterateNested($query, $definition, $root, $context, $negated);

        $wheres = $result->getWheres();

        $result->resetWheres();

        $glue = ' ' . $query->getOperator() . ' ';
        if (!empty($wheres)) {
            $result->addWhere('(' . implode($glue, $wheres) . ')');
        }

        return $result;
    }

    private function parseNotFilter(NotFilter $query, EntityDefinition $definition, string $root, Context $context): ParseResult
    {
        $result = $this->iterateNested($query, $definition, $root, $context, true);

        $wheres = $result->getWheres();

        $result->resetWheres();

        $glue = ' ' . $query->getOperator() . ' ';

        if (!empty($wheres)) {
            $result->addWhere('NOT (' . implode($glue, $wheres) . ')');
        }

        return $result;
    }

    private function iterateNested(MultiFilter $query, EntityDefinition $definition, string $root, Context $context, bool $negated): ParseResult
    {
        $result = new ParseResult();
        foreach ($query->getQueries() as $multiFilter) {
            $result = $result->merge(
                $this->parse($multiFilter, $definition, $context, $root, $negated)
            );
        }

        return $result;
    }

    private function getKey(): string
    {
        return 'param_' . Uuid::randomHex();
    }
}
