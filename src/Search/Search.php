<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Search;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

abstract class Search
{
    /**
     * @var HandlerInterface[]
     */
    protected $handlers;

    /**
     * @var Connection
     */
    protected $connection;

    public function __construct(Connection $connection, array $handlers)
    {
        $this->handlers = $handlers;
        $this->connection = $connection;
    }

    public function search(Criteria $criteria, TranslationContext $context): SearchResultInterface
    {
        $query = $this->createQuery($criteria, $context);

        if ($criteria->fetchCount()) {
            $selects = $query->getQueryPart('select');
            $selects[0] = 'SQL_CALC_FOUND_ROWS ' . $selects[0];
            $query->select($selects);
        }

        $this->addCriteriaPartToQuery($query, $criteria, $criteria->getConditions(), $context);
        $this->addCriteriaPartToQuery($query, $criteria, $criteria->getSortings(), $context);

        if ($criteria->getOffset()) {
            $query->setFirstResult($criteria->getOffset());
        }
        if ($criteria->getLimit()) {
            $query->setMaxResults($criteria->getLimit());
        }

        $rows = $this->fetchRows($query);

        if ($criteria->fetchCount()) {
            $total = $this->connection->fetchColumn('SELECT FOUND_ROWS()');
        } else {
            $total = count($rows);
        }

        return $this->createResult($rows, $total, $context);
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $facetResults = [];
        foreach ($criteria->getFacets() as $facet) {
            $query = $this->buildFacetQuery($criteria, $context);

            $handler = $this->getHandler($facet);

            $facetResults[] = $handler->aggregate($facet, $query, $criteria, $context);
        }

        return new AggregationResult($facetResults);
    }

    /**
     * Creates the base query with all fields which should be selected
     *
     * @param Criteria           $criteria
     * @param TranslationContext $context
     *
     * @return QueryBuilder
     */
    abstract protected function createQuery(Criteria $criteria, TranslationContext $context): QueryBuilder;

    /**
     * Hydrate the fetch rows and create a typed hint search result
     *
     * @param array $rows
     * @param int   $total
     *
     * @return SearchResultInterface
     */
    abstract protected function createResult(array $rows, int $total, TranslationContext $context): SearchResultInterface;

    protected function addCriteriaPartToQuery(QueryBuilder $query, Criteria $criteria, array $parts, TranslationContext $context): void
    {
        foreach ($parts as $part) {
            $handler = $this->getHandler($part);
            $handler->handle($part, $query, $criteria, $context);
        }
    }

    protected function getHandler(CriteriaPartInterface $criteriaPart)
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($criteriaPart)) {
                return $handler;
            }
        }
        throw new \RuntimeException(sprintf('No handler supports class %s', get_class($criteriaPart)));
    }

    protected function buildFacetQuery(Criteria $criteria, TranslationContext $context): QueryBuilder
    {
        $query = $this->createQuery($criteria, $context);

        if ($criteria->generatePartialFacets()) {
            $this->addCriteriaPartToQuery($query, $criteria, $criteria->getConditions(), $context);
        } else {
            $this->addCriteriaPartToQuery($query, $criteria, $criteria->getBaseConditions(), $context);
        }

        return $query;
    }

    /**
     * todo@next remove this function, only for simple debugging
     *
     * @param $query
     *
     * @return array
     */
    protected function fetchRows(QueryBuilder $query): array
    {
        return $query->execute()->fetchAll();
    }
}
