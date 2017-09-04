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

namespace Shopware\ProductStream\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ListingSorting\Reader\Query\ListingSortingBasicQuery;

class ProductStreamBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('product_stream', 'productStream');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect(
            [
                'productStream.uuid as _array_key_',
                'productStream.id as __productStream_id',
                'productStream.uuid as __productStream_uuid',
                'productStream.name as __productStream_name',
                'productStream.conditions as __productStream_conditions',
                'productStream.type as __productStream_type',
                'productStream.description as __productStream_description',
                'productStream.listing_sorting_id as __productStream_listing_sorting_id',
                'productStream.listing_sorting_uuid as __productStream_listing_sorting_uuid',
            ]
        );

        //$query->leftJoin('productStream', 'productStream_translation', 'productStreamTranslation', 'productStream.uuid = productStreamTranslation.productStream_uuid AND productStreamTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());

        $query->leftJoin(
            'productStream',
            'listing_sorting',
            'listingSorting',
            'listingSorting.uuid = productStream.listing_sorting_uuid'
        );
        ListingSortingBasicQuery::addRequirements($query, $context);
    }
}
