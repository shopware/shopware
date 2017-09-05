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

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregatorInterface;
use Shopware\Search\Condition\ActiveCondition;
use Shopware\Search\Criteria;
use Shopware\Search\CriteriaPartInterface;
use Shopware\Search\Facet\ActiveFacet;
use Shopware\Search\FacetResult\BooleanFacetResult;
use Shopware\Search\HandlerInterface;
use Shopware\Search\Sorting\ActiveSorting;

class ActiveHandler implements HandlerInterface, AggregatorInterface
{
    public function supports(CriteriaPartInterface $criteriaPart): bool
    {
        return
            $criteriaPart instanceof ActiveSorting
 || $criteriaPart instanceof ActiveCondition
 || $criteriaPart instanceof ActiveFacet
        ;
    }

    public function handle(
        CriteriaPartInterface $criteriaPart,
        QueryBuilder $builder,
        Criteria $criteria,
        TranslationContext $context
    ): void {
        if ($criteriaPart instanceof ActiveSorting) {
            $builder->addOrderBy('product.active', $criteriaPart->getDirection());

            return;
        }

                /* @var ActiveCondition $criteriaPart */
        $builder->andWhere('product.active = :active_condition');
        $builder->setParameter('active_condition', $criteriaPart->isActive());
    }

    public function aggregate(
        CriteriaPartInterface $criteriaPart,
        QueryBuilder $builder,
        Criteria $criteria,
        TranslationContext $context
    ) {
        $builder->groupBy('product.active');

        $builder->select(['product.active', 'COUNT(product.uuid) as item_count']);

        $counts = $builder->execute()->fetchAll(\PDO::FETCH_KEY_PAIR);

        $true = 0;
        $false = 0;
        foreach ($counts as $key => $count) {
            if ($key === 1) {
                $true = $count;
            } else {
                $false = $count;
            }
        }

        return new BooleanFacetResult($criteriaPart->getName(), $true, $false);
    }
}
