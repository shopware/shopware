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

namespace Shopware\ShippingMethodPrice\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class ShippingMethodPriceBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('shipping_method_price', 'shippingMethodPrice');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect([
            'shippingMethodPrice.uuid as _array_key_',
            'shippingMethodPrice.id as __shippingMethodPrice_id',
            'shippingMethodPrice.uuid as __shippingMethodPrice_uuid',
            'shippingMethodPrice.from as __shippingMethodPrice_from',
            'shippingMethodPrice.value as __shippingMethodPrice_value',
            'shippingMethodPrice.factor as __shippingMethodPrice_factor',
            'shippingMethodPrice.shipping_method_id as __shippingMethodPrice_shipping_method_id',
            'shippingMethodPrice.shipping_method_uuid as __shippingMethodPrice_shipping_method_uuid',
        ]);

        //$query->leftJoin('shippingMethodPrice', 'shippingMethodPrice_translation', 'shippingMethodPriceTranslation', 'shippingMethodPrice.uuid = shippingMethodPriceTranslation.shippingMethodPrice_uuid AND shippingMethodPriceTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
