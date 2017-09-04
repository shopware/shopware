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

namespace Shopware\SeoUrl\Searcher\Handler;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregatorInterface;
use Shopware\Search\Condition\IsCanonicalCondition;
use Shopware\Search\Criteria;
use Shopware\Search\CriteriaPartInterface;
use Shopware\Search\Facet\IsCanonicalFacet;
use Shopware\Search\FacetResult\BooleanFacetResult;
use Shopware\Search\HandlerInterface;
use Shopware\Search\Sorting\IsCanonicalSorting;

class IsCanonicalHandler implements HandlerInterface, AggregatorInterface
{
    public function supports(CriteriaPartInterface $criteriaPart): bool
    {
        return
            $criteriaPart instanceof IsCanonicalSorting
            || $criteriaPart instanceof IsCanonicalCondition
            || $criteriaPart instanceof IsCanonicalFacet;
    }

    public function handle(
        CriteriaPartInterface $criteriaPart,
        QueryBuilder $builder,
        Criteria $criteria,
        TranslationContext $context
    ): void {
        if ($criteriaPart instanceof IsCanonicalSorting) {
            $builder->addOrderBy('seoUrl.is_canonical', $criteriaPart->getDirection());

            return;
        }

        /* @var IsCanonicalCondition $criteriaPart */
        $builder->andWhere('seoUrl.is_canonical = :is_canonical_condition');
        $builder->setParameter('is_canonical_condition', $criteriaPart->isIsCanonical());
    }

    public function aggregate(
        CriteriaPartInterface $criteriaPart,
        QueryBuilder $builder,
        Criteria $criteria,
        TranslationContext $context
    ) {
        $builder->groupBy('seoUrl.is_canonical');

        $builder->select(['seoUrl.is_canonical', 'COUNT(seoUrl.uuid) as item_count']);

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
