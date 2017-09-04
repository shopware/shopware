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

namespace Shopware\SeoUrl\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;

class SeoUrlBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('seo_url', 'seoUrl');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect(
            [
                'seoUrl.uuid as _array_key_',
                'seoUrl.id as __seoUrl_id',
                'seoUrl.uuid as __seoUrl_uuid',
                'seoUrl.seo_hash as __seoUrl_seo_hash',
                'seoUrl.shop_uuid as __seoUrl_shop_uuid',
                'seoUrl.name as __seoUrl_name',
                'seoUrl.foreign_key as __seoUrl_foreign_key',
                'seoUrl.path_info as __seoUrl_path_info',
                'seoUrl.seo_path_info as __seoUrl_seo_path_info',
                'seoUrl.is_canonical as __seoUrl_is_canonical',
                'seoUrl.created_at as __seoUrl_created_at',
            ]
        );

        //$query->leftJoin('seoUrl', 'seoUrl_translation', 'seoUrlTranslation', 'seoUrl.uuid = seoUrlTranslation.seoUrl_uuid AND seoUrlTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());
    }
}
