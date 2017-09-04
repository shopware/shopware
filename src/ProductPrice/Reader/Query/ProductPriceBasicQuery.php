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

namespace Shopware\ProductPrice\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class ProductPriceBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('product_price', 'productPrice');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect(
            [
                'productPrice.uuid as _array_key_',
                'productPrice.id as __productPrice_id',
                'productPrice.uuid as __productPrice_uuid',
                'productPrice.pricegroup as __productPrice_pricegroup',
                'productPrice.from as __productPrice_from',
                'productPrice.to as __productPrice_to',
                'productPrice.product_id as __productPrice_product_id',
                'productPrice.product_uuid as __productPrice_product_uuid',
                'productPrice.product_detail_id as __productPrice_product_detail_id',
                'productPrice.product_detail_uuid as __productPrice_product_detail_uuid',
                'productPrice.price as __productPrice_price',
                'productPrice.pseudoprice as __productPrice_pseudoprice',
                'productPrice.baseprice as __productPrice_baseprice',
                'productPrice.percent as __productPrice_percent',
            ]
        );

        //$query->leftJoin('productPrice', 'productPrice_translation', 'productPriceTranslation', 'productPrice.uuid = productPriceTranslation.productPrice_uuid AND productPriceTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
