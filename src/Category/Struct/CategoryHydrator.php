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

namespace Shopware\Category\Struct;

use Shopware\Framework\Struct\AttributeHydrator;
use Shopware\ProductStream\Struct\ProductStreamHydrator;
use Shopware\SeoUrl\Struct\SeoUrlHydrator;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CategoryHydrator
{
    /**
     * @var AttributeHydrator
     */
    private $attributeHydrator;

    /**
     * @var ProductStreamHydrator
     */
    private $productStreamHydrator;

    /**
     * @var SeoUrlHydrator
     */
    private $seoUrlHydrator;

    public function __construct(
        AttributeHydrator $attributeHydrator,
        ProductStreamHydrator $productStreamHydrator,
        SeoUrlHydrator $seoUrlHydrator
    ) {
        $this->attributeHydrator = $attributeHydrator;
        $this->productStreamHydrator = $productStreamHydrator;
        $this->seoUrlHydrator = $seoUrlHydrator;
    }

    public function hydrateIdentity(array $data): CategoryIdentity
    {
        $identity = new CategoryIdentity();
        $this->assignData($data, $identity);

        return $identity;
    }

    public function hydrate(array $data): Category
    {
        $category = new Category();
        $this->assignData($data, $category);

//        if ($data['__category_media_id']) {
//            $category->setMedia(
//                $this->mediaHydrator->hydrate($data)
//            );
//        }

        if ($data['__category_stream_id']) {
            $category->setProductStream(
                $this->productStreamHydrator->hydrate($data)
            );
        }


        return $category;
    }

    private function assignData(array $data, CategoryIdentity $identity): void
    {
        $identity->assign([
            'id' => (int) $data['__category_id'],
            'uuid' => $data['__category_uuid'],
            'parent' => $data['__category_parent'] ? (int) $data['__category_parent'] : null,
            'path' => array_filter(explode('|', $data['__category_path'])),
            'name' => (string) $data['__category_description'],
            'position' => (int) $data['__category_position'],
            'level' => (int) $data['__category_level'],
            'added' => new \DateTime($data['__category_added']),
            'changedAt' => new \DateTime($data['__category_changed_at']),
            'metaKeywords' => $data['__category_meta_keywords'],
            'metaTitle' => $data['__category_meta_title'],
            'metaDescription' => $data['__category_meta_description'],
            'cmsHeadline' => $data['__category_cms_headline'],
            'cmsDescription' => $data['__category_cms_description'],
            'template' => $data['__category_template'],
            'active' => (bool) $data['__category_active'],
            'blog' => (bool) $data['__category_blog'],
            'external' => $data['__category_external'],
            'hideFilter' => (bool) $data['__category_hide_filter'],
            'hideTop' => (bool) $data['__category_hide_top'],
            'mediaId' => $data['__category_media_id'] ? (int) $data['__category_media_id']: null,
            'mediaUuid' => $data['__category_media_uuid'],
            'productBoxLayout' => $data['__category_product_box_layout'],
            'streamId' => $data['__category_stream_id'] ? (int) $data['__category_stream_id'] : null,
            'hideSortings' => (bool) $data['__category_hide_sortings'],
            'sortingIds' => array_filter(explode('|', $data['__category_sorting_ids'])),
            'facetIds' => array_filter(explode('|', $data['__category_facet_ids'])),
            'isShopCategory' => (bool) $data['__category_is_shop_category']
        ]);

        if ($data['__categoryAttribute_id']) {
            $this->attributeHydrator->addAttribute($identity, $data, 'categoryAttribute');
        }

        if ($data['__seoUrl_id']) {
            $identity->setSeoUrl($this->seoUrlHydrator->hydrate($data));
        }
    }
}
