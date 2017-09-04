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

namespace Shopware\Customer\Searcher\Handler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\AggregatorInterface;
use Shopware\Search\Condition\AreaCountryUuidCondition;
use Shopware\Search\Criteria;
use Shopware\Search\CriteriaPartInterface;
use Shopware\Search\Facet\AreaCountryUuidFacet;
use Shopware\Search\FacetResult\ArrayFacetResult;
use Shopware\Search\HandlerInterface;
use Shopware\Search\Sorting\AreaCountryUuidSorting;

class AreaCountryUuidHandler implements HandlerInterface, AggregatorInterface
{
    public function supports(CriteriaPartInterface $criteriaPart): bool
    {
        return
            $criteriaPart instanceof AreaCountryUuidSorting
 || $criteriaPart instanceof AreaCountryUuidCondition
 || $criteriaPart instanceof AreaCountryUuidFacet
        ;
    }

    public function handle(
        CriteriaPartInterface $criteriaPart,
        QueryBuilder $builder,
        Criteria $criteria,
        TranslationContext $context
    ): void {
        if ($criteriaPart instanceof AreaCountryUuidSorting) {
            $builder->addOrderBy('customerAddress.area_country_uuid', $criteriaPart->getDirection());

            return;
        }

                /* @var AreaCountryUuidCondition $criteriaPart */
        $builder->andWhere('customerAddress.area_country_uuid IN (:area_country_uuid_condition)');
        $builder->setParameter('area_country_uuid_condition', $criteriaPart->getAreaCountryUuids(), Connection::PARAM_STR_ARRAY);
    }

    public function aggregate(
        CriteriaPartInterface $criteriaPart,
        QueryBuilder $builder,
        Criteria $criteria,
        TranslationContext $context
    ) {
        $builder->select(['DISTINCT customerAddress.area_country_uuid']);
        $values = $builder->execute()->fetchAll(\PDO::FETCH_COLUMN);

        return new ArrayFacetResult(
            $criteriaPart->getName(),
            $criteria->hasCondition($criteriaPart->getName()),
            $values
        );
    }
}
