<?php declare(strict_types=1);

namespace Shopware\Product\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\ExtensionRegistryInterface;
use Shopware\Api\Read\Factory;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Factory\CustomerGroupBasicFactory;
use Shopware\PriceGroup\Factory\PriceGroupBasicFactory;
use Shopware\PriceGroup\Struct\PriceGroupBasicStruct;
use Shopware\Product\Extension\ProductExtension;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\ProductListingPrice\Factory\ProductListingPriceBasicFactory;
use Shopware\ProductManufacturer\Factory\ProductManufacturerBasicFactory;
use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicStruct;
use Shopware\ProductPrice\Factory\ProductPriceBasicFactory;
use Shopware\SeoUrl\Factory\SeoUrlBasicFactory;
use Shopware\SeoUrl\Struct\SeoUrlBasicStruct;
use Shopware\Tax\Factory\TaxBasicFactory;
use Shopware\Tax\Struct\TaxBasicStruct;
use Shopware\Unit\Factory\UnitBasicFactory;
use Shopware\Unit\Struct\UnitBasicStruct;

class ProductBasicFactory extends Factory
{
    const ROOT_NAME = 'product';
    const EXTENSION_NAMESPACE = 'product';

    const FIELDS = [
       'uuid' => 'uuid',
       'containerUuid' => 'container_uuid',
       'isMain' => 'is_main',
       'active' => 'active',
       'taxUuid' => 'tax_uuid',
       'manufacturerUuid' => 'product_manufacturer_uuid',
       'priceGroupUuid' => 'price_group_uuid',
       'filterGroupUuid' => 'filter_group_uuid',
       'unitUuid' => 'unit_uuid',
       'supplierNumber' => 'supplier_number',
       'ean' => 'ean',
       'stock' => 'stock',
       'isCloseout' => 'is_closeout',
       'minStock' => 'min_stock',
       'purchaseSteps' => 'purchase_steps',
       'maxPurchase' => 'max_purchase',
       'minPurchase' => 'min_purchase',
       'purchaseUnit' => 'purchase_unit',
       'referenceUnit' => 'reference_unit',
       'shippingFree' => 'shipping_free',
       'purchasePrice' => 'purchase_price',
       'pseudoSales' => 'pseudo_sales',
       'markAsTopseller' => 'mark_as_topseller',
       'sales' => 'sales',
       'position' => 'position',
       'weight' => 'weight',
       'width' => 'width',
       'height' => 'height',
       'length' => 'length',
       'template' => 'template',
       'allowNotification' => 'allow_notification',
       'releaseDate' => 'release_date',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
       'additionalText' => 'translation.additional_text',
       'name' => 'translation.name',
       'keywords' => 'translation.keywords',
       'description' => 'translation.description',
       'descriptionLong' => 'translation.description_long',
       'metaTitle' => 'translation.meta_title',
       'packUnit' => 'translation.pack_unit',
    ];

    /**
     * @var UnitBasicFactory
     */
    protected $unitFactory;

    /**
     * @var ProductPriceBasicFactory
     */
    protected $productPriceFactory;

    /**
     * @var ProductManufacturerBasicFactory
     */
    protected $productManufacturerFactory;

    /**
     * @var TaxBasicFactory
     */
    protected $taxFactory;

    /**
     * @var SeoUrlBasicFactory
     */
    protected $seoUrlFactory;

    /**
     * @var PriceGroupBasicFactory
     */
    protected $priceGroupFactory;

    /**
     * @var CustomerGroupBasicFactory
     */
    protected $customerGroupFactory;

    /**
     * @var ProductListingPriceBasicFactory
     */
    protected $productListingPriceFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        UnitBasicFactory $unitFactory,
        ProductPriceBasicFactory $productPriceFactory,
        ProductManufacturerBasicFactory $productManufacturerFactory,
        TaxBasicFactory $taxFactory,
        SeoUrlBasicFactory $seoUrlFactory,
        PriceGroupBasicFactory $priceGroupFactory,
        CustomerGroupBasicFactory $customerGroupFactory,
        ProductListingPriceBasicFactory $productListingPriceFactory
    ) {
        parent::__construct($connection, $registry);
        $this->unitFactory = $unitFactory;
        $this->productPriceFactory = $productPriceFactory;
        $this->productManufacturerFactory = $productManufacturerFactory;
        $this->taxFactory = $taxFactory;
        $this->seoUrlFactory = $seoUrlFactory;
        $this->priceGroupFactory = $priceGroupFactory;
        $this->customerGroupFactory = $customerGroupFactory;
        $this->productListingPriceFactory = $productListingPriceFactory;
    }

    public function hydrate(
        array $data,
        ProductBasicStruct $product,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductBasicStruct {
        $product->setUuid((string) $data[$selection->getField('uuid')]);
        $product->setContainerUuid(isset($data[$selection->getField('containerUuid')]) ? (string) $data[$selection->getField('containerUuid')] : null);
        $product->setIsMain((bool) $data[$selection->getField('isMain')]);
        $product->setActive((bool) $data[$selection->getField('active')]);
        $product->setTaxUuid(isset($data[$selection->getField('taxUuid')]) ? (string) $data[$selection->getField('taxUuid')] : null);
        $product->setManufacturerUuid(isset($data[$selection->getField('manufacturerUuid')]) ? (string) $data[$selection->getField('manufacturerUuid')] : null);
        $product->setPriceGroupUuid(isset($data[$selection->getField('priceGroupUuid')]) ? (string) $data[$selection->getField('priceGroupUuid')] : null);
        $product->setFilterGroupUuid(isset($data[$selection->getField('filterGroupUuid')]) ? (string) $data[$selection->getField('filterGroupUuid')] : null);
        $product->setUnitUuid(isset($data[$selection->getField('unitUuid')]) ? (string) $data[$selection->getField('unitUuid')] : null);
        $product->setSupplierNumber(isset($data[$selection->getField('supplierNumber')]) ? (string) $data[$selection->getField('supplierNumber')] : null);
        $product->setEan(isset($data[$selection->getField('ean')]) ? (string) $data[$selection->getField('ean')] : null);
        $product->setStock((int) $data[$selection->getField('stock')]);
        $product->setIsCloseout((bool) $data[$selection->getField('isCloseout')]);
        $product->setMinStock(isset($data[$selection->getField('minStock')]) ? (int) $data[$selection->getField('minStock')] : null);
        $product->setPurchaseSteps(isset($data[$selection->getField('purchaseSteps')]) ? (int) $data[$selection->getField('purchaseSteps')] : null);
        $product->setMaxPurchase(isset($data[$selection->getField('maxPurchase')]) ? (int) $data[$selection->getField('maxPurchase')] : null);
        $product->setMinPurchase((int) $data[$selection->getField('minPurchase')]);
        $product->setPurchaseUnit(isset($data[$selection->getField('purchaseUnit')]) ? (float) $data[$selection->getField('purchaseUnit')] : null);
        $product->setReferenceUnit(isset($data[$selection->getField('referenceUnit')]) ? (float) $data[$selection->getField('referenceUnit')] : null);
        $product->setShippingFree((bool) $data[$selection->getField('shippingFree')]);
        $product->setPurchasePrice((float) $data[$selection->getField('purchasePrice')]);
        $product->setPseudoSales((int) $data[$selection->getField('pseudoSales')]);
        $product->setMarkAsTopseller((bool) $data[$selection->getField('markAsTopseller')]);
        $product->setSales((int) $data[$selection->getField('sales')]);
        $product->setPosition((int) $data[$selection->getField('position')]);
        $product->setWeight(isset($data[$selection->getField('weight')]) ? (float) $data[$selection->getField('weight')] : null);
        $product->setWidth(isset($data[$selection->getField('width')]) ? (float) $data[$selection->getField('width')] : null);
        $product->setHeight(isset($data[$selection->getField('height')]) ? (float) $data[$selection->getField('height')] : null);
        $product->setLength(isset($data[$selection->getField('length')]) ? (float) $data[$selection->getField('length')] : null);
        $product->setTemplate(isset($data[$selection->getField('template')]) ? (string) $data[$selection->getField('template')] : null);
        $product->setAllowNotification((bool) $data[$selection->getField('allowNotification')]);
        $product->setReleaseDate(isset($data[$selection->getField('releaseDate')]) ? new \DateTime($data[$selection->getField('releaseDate')]) : null);
        $product->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $product->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $product->setAdditionalText(isset($data[$selection->getField('additionalText')]) ? (string) $data[$selection->getField('additionalText')] : null);
        $product->setName((string) $data[$selection->getField('name')]);
        $product->setKeywords(isset($data[$selection->getField('keywords')]) ? (string) $data[$selection->getField('keywords')] : null);
        $product->setDescription(isset($data[$selection->getField('description')]) ? (string) $data[$selection->getField('description')] : null);
        $product->setDescriptionLong(isset($data[$selection->getField('descriptionLong')]) ? (string) $data[$selection->getField('descriptionLong')] : null);
        $product->setMetaTitle(isset($data[$selection->getField('metaTitle')]) ? (string) $data[$selection->getField('metaTitle')] : null);
        $product->setPackUnit(isset($data[$selection->getField('packUnit')]) ? (string) $data[$selection->getField('packUnit')] : null);
        $unit = $selection->filter('unit');
        if ($unit && !empty($data[$unit->getField('uuid')])) {
            $product->setUnit(
                $this->unitFactory->hydrate($data, new UnitBasicStruct(), $unit, $context)
            );
        }
        $productManufacturer = $selection->filter('manufacturer');
        if ($productManufacturer && !empty($data[$productManufacturer->getField('uuid')])) {
            $product->setManufacturer(
                $this->productManufacturerFactory->hydrate($data, new ProductManufacturerBasicStruct(), $productManufacturer, $context)
            );
        }
        $tax = $selection->filter('tax');
        if ($tax && !empty($data[$tax->getField('uuid')])) {
            $product->setTax(
                $this->taxFactory->hydrate($data, new TaxBasicStruct(), $tax, $context)
            );
        }
        $seoUrl = $selection->filter('canonicalUrl');
        if ($seoUrl && !empty($data[$seoUrl->getField('uuid')])) {
            $product->setCanonicalUrl(
                $this->seoUrlFactory->hydrate($data, new SeoUrlBasicStruct(), $seoUrl, $context)
            );
        }
        $priceGroup = $selection->filter('priceGroup');
        if ($priceGroup && !empty($data[$priceGroup->getField('uuid')])) {
            $product->setPriceGroup(
                $this->priceGroupFactory->hydrate($data, new PriceGroupBasicStruct(), $priceGroup, $context)
            );
        }
        if ($selection->hasField('_sub_select_blockedCustomerGroups_uuids')) {
            $uuids = explode('|', (string) $data[$selection->getField('_sub_select_blockedCustomerGroups_uuids')]);
            $product->setBlockedCustomerGroupsUuids(array_values(array_filter($uuids)));
        }

        /** @var $extension ProductExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($product, $data, $selection, $context);
        }

        return $product;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        $fields['unit'] = $this->unitFactory->getFields();
        $fields['manufacturer'] = $this->productManufacturerFactory->getFields();
        $fields['tax'] = $this->taxFactory->getFields();
        $fields['canonicalUrl'] = $this->seoUrlFactory->getFields();
        $fields['priceGroup'] = $this->priceGroupFactory->getFields();
        $fields['_sub_select_blockedCustomerGroups_uuids'] = '_sub_select_blockedCustomerGroups_uuids';

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        $this->joinUnit($selection, $query, $context);
        $this->joinPrices($selection, $query, $context);
        $this->joinManufacturer($selection, $query, $context);
        $this->joinTax($selection, $query, $context);
        $this->joinCanonicalUrl($selection, $query, $context);
        $this->joinPriceGroup($selection, $query, $context);
        $this->joinBlockedCustomerGroups($selection, $query, $context);
        $this->joinListingPrices($selection, $query, $context);
        $this->joinTranslation($selection, $query, $context);

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());
        $fields['unit'] = $this->unitFactory->getAllFields();
        $fields['prices'] = $this->productPriceFactory->getAllFields();
        $fields['manufacturer'] = $this->productManufacturerFactory->getAllFields();
        $fields['tax'] = $this->taxFactory->getAllFields();
        $fields['canonicalUrl'] = $this->seoUrlFactory->getAllFields();
        $fields['priceGroup'] = $this->priceGroupFactory->getAllFields();
        $fields['blockedCustomerGroups'] = $this->customerGroupFactory->getAllFields();
        $fields['listingPrices'] = $this->productListingPriceFactory->getAllFields();

        return $fields;
    }

    protected function getRootName(): string
    {
        return self::ROOT_NAME;
    }

    protected function getExtensionNamespace(): string
    {
        return self::EXTENSION_NAMESPACE;
    }

    private function joinUnit(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($unit = $selection->filter('unit'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'unit',
            $unit->getRootEscaped(),
            sprintf('%s.uuid = %s.unit_uuid', $unit->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->unitFactory->joinDependencies($unit, $query, $context);
    }

    private function joinPrices(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($prices = $selection->filter('prices'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'product_price',
            $prices->getRootEscaped(),
            sprintf('%s.uuid = %s.product_uuid', $selection->getRootEscaped(), $prices->getRootEscaped())
        );

        $this->productPriceFactory->joinDependencies($prices, $query, $context);

        $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
    }

    private function joinManufacturer(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($productManufacturer = $selection->filter('manufacturer'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'product_manufacturer',
            $productManufacturer->getRootEscaped(),
            sprintf('%s.uuid = %s.product_manufacturer_uuid', $productManufacturer->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->productManufacturerFactory->joinDependencies($productManufacturer, $query, $context);
    }

    private function joinTax(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($tax = $selection->filter('tax'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'tax',
            $tax->getRootEscaped(),
            sprintf('%s.uuid = %s.tax_uuid', $tax->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->taxFactory->joinDependencies($tax, $query, $context);
    }

    private function joinCanonicalUrl(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!$canonical = $selection->filter('canonicalUrl')) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'seo_url',
            $canonical->getRootEscaped(),
            sprintf('%1$s.uuid = %2$s.foreign_key AND %2$s.name = :productSeoName AND %2$s.is_canonical = 1 AND %2$s.shop_uuid = :shopUuid', $selection->getRootEscaped(), $canonical->getRootEscaped())
        );
        $query->setParameter('productSeoName', 'detail_page');
        $query->setParameter('shopUuid', $context->getShopUuid());
    }

    private function joinPriceGroup(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($priceGroup = $selection->filter('priceGroup'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'price_group',
            $priceGroup->getRootEscaped(),
            sprintf('%s.uuid = %s.price_group_uuid', $priceGroup->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->priceGroupFactory->joinDependencies($priceGroup, $query, $context);
    }

    private function joinBlockedCustomerGroups(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if ($selection->hasField('_sub_select_blockedCustomerGroups_uuids')) {
            $query->addSelect('
                (
                    SELECT GROUP_CONCAT(mapping.customer_group_uuid SEPARATOR \'|\')
                    FROM product_avoid_customer_group mapping
                    WHERE mapping.product_uuid = ' . $selection->getRootEscaped() . '.uuid
                ) as ' . QuerySelection::escape($selection->getField('_sub_select_blockedCustomerGroups_uuids'))
            );
        }

        if (!($blockedCustomerGroups = $selection->filter('blockedCustomerGroups'))) {
            return;
        }

        $mapping = QuerySelection::escape($blockedCustomerGroups->getRoot() . '.mapping');

        $query->leftJoin(
            $selection->getRootEscaped(),
            'product_avoid_customer_group',
            $mapping,
            sprintf('%s.uuid = %s.product_uuid', $selection->getRootEscaped(), $mapping)
        );
        $query->leftJoin(
            $mapping,
            'customer_group',
            $blockedCustomerGroups->getRootEscaped(),
            sprintf('%s.customer_group_uuid = %s.uuid', $mapping, $blockedCustomerGroups->getRootEscaped())
        );

        $this->customerGroupFactory->joinDependencies($blockedCustomerGroups, $query, $context);

        $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
    }

    private function joinListingPrices(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($listingPrices = $selection->filter('listingPrices'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'product_listing_price_ro',
            $listingPrices->getRootEscaped(),
            sprintf('%s.uuid = %s.product_uuid', $selection->getRootEscaped(), $listingPrices->getRootEscaped())
        );

        $this->productListingPriceFactory->joinDependencies($listingPrices, $query, $context);

        $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
    }

    private function joinTranslation(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($translation = $selection->filter('translation'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'product_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.product_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
