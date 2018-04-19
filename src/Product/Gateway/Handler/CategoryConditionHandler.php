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

namespace Shopware\Product\Gateway\Handler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Condition\CategoryCondition;
use Shopware\Search\Criteria;
use Shopware\Search\CriteriaPartInterface;
use Shopware\Search\HandlerInterface;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CategoryConditionHandler implements HandlerInterface
{
    /**
     * @var int
     */
    private $counter = 0;

    public function supports(CriteriaPartInterface $criteriaPart): bool
    {
        return $criteriaPart instanceof CategoryCondition;
    }

    public function handle(
        CriteriaPartInterface $criteriaPart,
        QueryBuilder $builder,
        Criteria $criteria,
        TranslationContext $context
    ): void {
        /* @var CategoryCondition $criteriaPart */
        if ($this->counter++ === 0) {
            $suffix = '';
        } else {
            $suffix = $this->counter;
        }

        $builder->innerJoin(
            'product',
            'product_category_ro',
            "productCategory{$suffix}",
            "productCategory{$suffix}.product_uuid = product.uuid
            AND productCategory{$suffix}.category_uuid IN (:category{$suffix})"
        );

        $builder->setParameter(
            ":category{$suffix}",
            $criteriaPart->getCategoryUuids(),
            Connection::PARAM_STR_ARRAY
        );
    }
}
