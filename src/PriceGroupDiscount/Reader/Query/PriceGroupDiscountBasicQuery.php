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

namespace Shopware\PriceGroupDiscount\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class PriceGroupDiscountBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('price_group_discount', 'priceGroupDiscount');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect(
            [
                'priceGroupDiscount.uuid as _array_key_',
                'priceGroupDiscount.uuid as __priceGroupDiscount_uuid',
                'priceGroupDiscount.price_group_uuid as __priceGroupDiscount_price_group_uuid',
                'priceGroupDiscount.customer_group_uuid as __priceGroupDiscount_customer_group_uuid',
                'priceGroupDiscount.discount as __priceGroupDiscount_discount',
                'priceGroupDiscount.discount_start as __priceGroupDiscount_discount_start',
            ]
        );

        //$query->leftJoin('priceGroupDiscount', 'priceGroupDiscount_translation', 'priceGroupDiscountTranslation', 'priceGroupDiscount.uuid = priceGroupDiscountTranslation.priceGroupDiscount_uuid AND priceGroupDiscountTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
