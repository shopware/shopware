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

namespace Shopware\Product\Reader;

use Shopware\Framework\Struct\Hydrator;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\ProductDetail\Reader\ProductDetailBasicHydrator;
use Shopware\ProductManufacturer\Reader\ProductManufacturerBasicHydrator;
use Shopware\SeoUrl\Reader\SeoUrlBasicHydrator;
use Shopware\Tax\Reader\TaxBasicHydrator;

class ProductBasicHydrator extends Hydrator
{
    /**
     * @var ProductManufacturerBasicHydrator
     */
    private $productManufacturerBasicHydrator;

    /**
     * @var ProductDetailBasicHydrator
     */
    private $productDetailBasicHydrator;

    /**
     * @var TaxBasicHydrator
     */
    private $taxBasicHydrator;

    /**
     * @var SeoUrlBasicHydrator
     */
    private $seoUrlBasicHydrator;

    public function __construct(
        ProductManufacturerBasicHydrator $productManufacturerBasicHydrator,
        ProductDetailBasicHydrator $productDetailBasicHydrator,
        TaxBasicHydrator $taxBasicHydrator,
        SeoUrlBasicHydrator $seoUrlBasicHydrator
    ) {
        $this->productManufacturerBasicHydrator = $productManufacturerBasicHydrator;
        $this->productDetailBasicHydrator = $productDetailBasicHydrator;
        $this->taxBasicHydrator = $taxBasicHydrator;
        $this->seoUrlBasicHydrator = $seoUrlBasicHydrator;
    }

    public function hydrate(array $data): ProductBasicStruct
    {
        $product = new ProductBasicStruct();

        $product->setUuid((string)$data['__product_uuid']);
        $product->setName((string) $data['__productTranslation_name']);
        $product->setKeywords(isset($data['__productTranslation_keywords']) ? (string) $data['__productTranslation_keywords'] : null);
        $product->setDescription(isset($data['__productTranslation_description']) ? (string) $data['__productTranslation_description'] : null);
        $product->setDescriptionLong(isset($data['__productTranslation_description_long']) ? (string) $data['__productTranslation_description_long'] : null);
        $product->setMetaTitle(isset($data['__productTranslation_meta_title']) ? (string) $data['__productTranslation_meta_title'] : null);
        
        $product->setManufacturerUuid((string)$data['__product_product_manufacturer_uuid']);
        $product->setShippingTime(
            isset($data['__product_shipping_time']) ? (string)$data['__product_shipping_time'] : null
        );
        $product->setCreatedAt(
            isset($data['__product_created_at']) ? new \DateTime($data['__product_created_at']) : null
        );
        $product->setActive((bool)$data['__product_active']);
        $product->setTaxUuid((string)$data['__product_tax_uuid']);
        $product->setMainDetailUuid(
            isset($data['__product_main_detail_uuid']) ? (string)$data['__product_main_detail_uuid'] : null
        );
        $product->setPseudoSales((int)$data['__product_pseudo_sales']);
        $product->setTopseller((bool)$data['__product_topseller']);
        $product->setUpdatedAt(new \DateTime($data['__product_updated_at']));
        $product->setPriceGroupId(
            isset($data['__product_price_group_id']) ? (int)$data['__product_price_group_id'] : null
        );
        $product->setFilterGroupUuid(
            isset($data['__product_filter_group_uuid']) ? (string)$data['__product_filter_group_uuid'] : null
        );
        $product->setLastStock((bool)$data['__product_last_stock']);
        $product->setNotification((bool)$data['__product_notification']);
        $product->setTemplate((string)$data['__product_template']);
        $product->setMode((int)$data['__product_mode']);
        $product->setAvailableFrom(
            isset($data['__product_available_from']) ? new \DateTime($data['__product_available_from']) : null
        );
        $product->setAvailableTo(
            isset($data['__product_available_to']) ? new \DateTime($data['__product_available_to']) : null
        );
        $product->setConfiguratorSetId(
            isset($data['__product_configurator_set_id']) ? (int)$data['__product_configurator_set_id'] : null
        );
        $product->setManufacturer($this->productManufacturerBasicHydrator->hydrate($data));
        $product->setMainDetail($this->productDetailBasicHydrator->hydrate($data));
        $product->setTax($this->taxBasicHydrator->hydrate($data));

        if (!empty($data['__seoUrl_uuid'])) {
            $product->setCanonicalUrl($this->seoUrlBasicHydrator->hydrate($data));
        }

        return $product;
    }
}
