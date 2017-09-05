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

namespace Shopware\Product\Searcher\Handler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregatorInterface;
use Shopware\Search\Condition\CategoryUuidCondition;
use Shopware\Search\Criteria;
use Shopware\Search\CriteriaPartInterface;
use Shopware\Search\Facet\CategoryUuidFacet;
use Shopware\Search\FacetResult\ArrayFacetResult;
use Shopware\Search\HandlerInterface;
use Shopware\Search\Sorting\CategoryUuidSorting;

class CategoryUuidHandler implements HandlerInterface, AggregatorInterface
{
    public function supports(CriteriaPartInterface $criteriaPart): bool
    {
        return
            $criteriaPart instanceof CategoryUuidSorting
         || $criteriaPart instanceof CategoryUuidCondition
         || $criteriaPart instanceof CategoryUuidFacet
        ;
    }

    public function handle(
        CriteriaPartInterface $criteriaPart,
        QueryBuilder $builder,
        Criteria $criteria,
        TranslationContext $context
    ): void {
        $this->joinCategories($builder);
        if ($criteriaPart instanceof CategoryUuidSorting) {
            $builder->addOrderBy('product_category.category_uuid', $criteriaPart->getDirection());

            return;
        }

                /* @var CategoryUuidCondition $criteriaPart */
        $builder->andWhere('product_category.category_uuid IN (:category_uuid_condition)');
        $builder->setParameter('category_uuid_condition', $criteriaPart->getCategoryUuids(), Connection::PARAM_STR_ARRAY);
    }

    public function aggregate(
        CriteriaPartInterface $criteriaPart,
        QueryBuilder $builder,
        Criteria $criteria,
        TranslationContext $context
    ) {
        $this->joinCategories($builder);
        $builder->select(['DISTINCT product_category.category_uuid']);

        $values = $builder->execute()->fetchAll(\PDO::FETCH_COLUMN);

        return new ArrayFacetResult($criteriaPart->getName(), $criteria->hasCondition($criteriaPart->getName()), $values);
    }

    private function joinCategories(QueryBuilder $builder)
    {
        $builder->innerJoin('product', 'product_category_ro', 'product_category', 'product_category.product_uuid = product.uuid');
    }
}
