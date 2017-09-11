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

namespace Shopware\Category\Reader;

use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Framework\Struct\Hydrator;
use Shopware\SeoUrl\Reader\SeoUrlBasicHydrator;

class CategoryBasicHydrator extends Hydrator
{
    /**
     * @var SeoUrlBasicHydrator
     */
    private $seoUrlBasicHydrator;

    public function __construct(SeoUrlBasicHydrator $seoUrlBasicHydrator)
    {
        $this->seoUrlBasicHydrator = $seoUrlBasicHydrator;
    }

    public function hydrate(array $data): CategoryBasicStruct
    {
        $category = new CategoryBasicStruct();

        $category->setUuid((string)$data['__category_uuid']);
        $category->setParentUuid(
            isset($data['__category_parent_uuid']) ? (string)$data['__category_parent_uuid'] : null
        );
        $category->setPath(array_filter(explode('|', (string)$data['__category_path'])));
        $category->setName((string)$data['__category_name']);
        $category->setPosition(isset($data['__category_position']) ? (int)$data['__category_position'] : null);
        $category->setLevel((int)$data['__category_level']);
        $category->setAdded(new \DateTime($data['__category_added']));
        $category->setChangedAt(new \DateTime($data['__category_changed_at']));
        $category->setMetaKeywords(
            isset($data['__category_meta_keywords']) ? (string)$data['__category_meta_keywords'] : null
        );
        $category->setMetaTitle(isset($data['__category_meta_title']) ? (string)$data['__category_meta_title'] : null);
        $category->setMetaDescription(
            isset($data['__category_meta_description']) ? (string)$data['__category_meta_description'] : null
        );
        $category->setCmsHeadline(
            isset($data['__category_cms_headline']) ? (string)$data['__category_cms_headline'] : null
        );
        $category->setCmsDescription(
            isset($data['__category_cms_description']) ? (string)$data['__category_cms_description'] : null
        );
        $category->setTemplate(isset($data['__category_template']) ? (string)$data['__category_template'] : null);
        $category->setActive((bool)$data['__category_active']);
        $category->setIsBlog((bool)$data['__category_is_blog']);
        $category->setExternal(isset($data['__category_external']) ? (string)$data['__category_external'] : null);
        $category->setHideFilter((bool)$data['__category_hide_filter']);
        $category->setHideTop((bool)$data['__category_hide_top']);
        $category->setMediaId(isset($data['__category_media_id']) ? (int)$data['__category_media_id'] : null);
        $category->setMediaUuid((string)$data['__category_media_uuid']);
        $category->setProductBoxLayout(
            isset($data['__category_product_box_layout']) ? (string)$data['__category_product_box_layout'] : null
        );
        $category->setProductStreamUuid(
            isset($data['__category_product_stream_uuid']) ? (string)$data['__category_product_stream_uuid'] : null
        );
        $category->setHideSortings((bool)$data['__category_hide_sortings']);
        $category->setSortingIds(array_filter(explode('|', (string)$data['__category_sorting_ids'])));
        $category->setFacetIds(array_filter(explode('|', (string)$data['__category_facet_ids'])));

        if (!empty($data['__seoUrl_uuid'])) {
            $category->setCanonicalUrl($this->seoUrlBasicHydrator->hydrate($data));
        }

        return $category;
    }
}
