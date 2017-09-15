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
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\SqlParser\SqlParser;

abstract class Searcher
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var SqlParser
     */
    private $parser;

    public function __construct(Connection $connection, SqlParser $parser)
    {
        $this->connection = $connection;
        $this->parser = $parser;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        return new AggregationResult();
    }

    public function search(Criteria $criteria, TranslationContext $context): SearchResultInterface
    {
        $result = $this->searchUuids($criteria, $context);

        return $this->load($result, $context);
    }

    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        $query = $this->createQuery($criteria, $context);

        $parsed = $this->parser->parse(
            $criteria->getAllFilters(),
            $query->getSelection()
        );

        if ($criteria->fetchCount()) {
            $selects = $query->getQueryPart('select');
            $selects[0] = 'SQL_CALC_FOUND_ROWS ' . $selects[0];
            $query->select($selects);
        }

        if (!empty($parsed->getWheres())) {
            $query->andWhere(implode(' AND ', $parsed->getWheres()));
        }

        foreach ($parsed->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $parsed->getType($key));
        }

        foreach ($criteria->getSortings() as $sorting) {
            $query->addOrderBy(
                $query->getSelection()->getFieldEscaped($sorting->getField()),
                $sorting->getDirection()
            );
        }

        if ($criteria->getOffset()) {
            $query->setFirstResult($criteria->getOffset());
        }
        if ($criteria->getLimit()) {
            $query->setMaxResults($criteria->getLimit());
        }

        $uuids = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

        if ($criteria->fetchCount()) {
            $total = $this->connection->fetchColumn('SELECT FOUND_ROWS()');
        } else {
            $total = count($uuids);
        }

        return new UuidSearchResult($total, $uuids);
    }

    /**
     * @param Criteria $criteria
     * @param TranslationContext $context
     * @return QueryBuilder
     */
    abstract protected function createQuery(Criteria $criteria, TranslationContext $context): QueryBuilder;

    /**
     * Hydrate the fetch rows and create a typed hint search result
     *
     * @param UuidSearchResult $result
     * @param TranslationContext $context
     * @return SearchResultInterface
     */
    abstract protected function load(UuidSearchResult $result, TranslationContext $context): SearchResultInterface;
}
