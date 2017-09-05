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

namespace Shopware\Category\Reader\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\SeoUrl\Reader\Query\SeoUrlBasicQuery;
use Shopware\Storefront\ListingPage\ListingPageUrlGenerator;

class CategoryBasicQuery extends QueryBuilder
{
    public function __construct(Connection $connection, TranslationContext $context)
    {
        parent::__construct($connection);

        $this->from('category', 'category');

        self::addRequirements($this, $context);
    }

    public static function addRequirements(QueryBuilder $query, TranslationContext $context)
    {
        $query->addSelect(
            [
                'category.uuid as _array_key_',
                'category.id as __category_id',
                'category.uuid as __category_uuid',
                'category.parent_id as __category_parent_id',
                'category.parent_uuid as __category_parent_uuid',
                'category.path as __category_path',
                'category.name as __category_name',
                'category.position as __category_position',
                'category.level as __category_level',
                'category.added as __category_added',
                'category.changed_at as __category_changed_at',
                'category.meta_keywords as __category_meta_keywords',
                'category.meta_title as __category_meta_title',
                'category.meta_description as __category_meta_description',
                'category.cms_headline as __category_cms_headline',
                'category.cms_description as __category_cms_description',
                'category.template as __category_template',
                'category.active as __category_active',
                'category.is_blog as __category_is_blog',
                'category.external as __category_external',
                'category.hide_filter as __category_hide_filter',
                'category.hide_top as __category_hide_top',
                'category.media_id as __category_media_id',
                'category.media_uuid as __category_media_uuid',
                'category.product_box_layout as __category_product_box_layout',
                'category.product_stream_id as __category_product_stream_id',
                'category.product_stream_uuid as __category_product_stream_uuid',
                'category.hide_sortings as __category_hide_sortings',
                'category.sorting_ids as __category_sorting_ids',
                'category.facet_ids as __category_facet_ids',
            ]
        );

        //$query->leftJoin('category', 'category_translation', 'categoryTranslation', 'category.uuid = categoryTranslation.category_uuid AND categoryTranslation.language_uuid = :languageUuid');
        //$query->setParameter('languageUuid', $context->getShopUuid());

        $query->leftJoin('category', 'seo_url', 'seoUrl', 'category.uuid = seoUrl.foreign_key AND seoUrl.is_canonical = 1 AND seoUrl.shop_uuid = :shopUuid AND seoUrl.name = :categorySeoUrlName');

        $query->setParameter(':categorySeoUrlName', ListingPageUrlGenerator::ROUTE_NAME);
        $query->setParameter(':shopUuid', $context->getShopUuid());

        SeoUrlBasicQuery::addRequirements($query, $context);
    }
}
