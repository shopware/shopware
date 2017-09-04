<?php declare(strict_types=1);
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

namespace Shopware\Area\Searcher;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Area\Reader\AreaBasicHydrator;
use Shopware\Area\Reader\Query\AreaBasicQuery;
use Shopware\Area\Struct\AreaSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\Search;
use Shopware\Search\SearchResultInterface;

class AreaSearcher extends Search
{
    /**
     * @var AreaBasicHydrator
     */
    private $hydrator;

    public function __construct(Connection $connection, array $handlers, AreaBasicHydrator $hydrator)
    {
        parent::__construct($connection, $handlers);
        $this->hydrator = $hydrator;
    }

    protected function createQuery(Criteria $criteria, TranslationContext $context): QueryBuilder
    {
        return new AreaBasicQuery($this->connection, $context);
    }

    protected function createResult(array $rows, int $total, TranslationContext $context): SearchResultInterface
    {
        $structs = array_map(function (array $row) {
            return $this->hydrator->hydrate($row);
        }, $rows);

        return new AreaSearchResult($structs, $total);
    }
}
