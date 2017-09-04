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

namespace Shopware\ProductManufacturer\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class ProductManufacturerBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('product_manufacturer', 'productManufacturer');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect([
            'productManufacturer.uuid as _array_key_',
            'productManufacturer.id as __productManufacturer_id',
            'productManufacturer.uuid as __productManufacturer_uuid',
            'productManufacturer.name as __productManufacturer_name',
            'productManufacturer.img as __productManufacturer_img',
            'productManufacturer.link as __productManufacturer_link',
            'productManufacturer.description as __productManufacturer_description',
            'productManufacturer.meta_title as __productManufacturer_meta_title',
            'productManufacturer.meta_description as __productManufacturer_meta_description',
            'productManufacturer.meta_keywords as __productManufacturer_meta_keywords',
            'productManufacturer.updated_at as __productManufacturer_updated_at',
        ]);

        //$query->leftJoin('productManufacturer', 'productManufacturer_translation', 'productManufacturerTranslation', 'productManufacturer.uuid = productManufacturerTranslation.productManufacturer_uuid AND productManufacturerTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
