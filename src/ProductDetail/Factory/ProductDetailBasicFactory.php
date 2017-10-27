<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\ExtensionRegistryInterface;
use Shopware\Api\Read\Factory;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductDetail\Extension\ProductDetailExtension;
use Shopware\ProductDetail\Struct\ProductDetailBasicStruct;
use Shopware\ProductDetailPrice\Factory\ProductDetailPriceBasicFactory;
use Shopware\Unit\Factory\UnitBasicFactory;
use Shopware\Unit\Struct\UnitBasicStruct;

class ProductDetailBasicFactory extends Factory
{
    const ROOT_NAME = 'product_detail';
    const EXTENSION_NAMESPACE = 'productDetail';

    const FIELDS = [
       'uuid' => 'uuid',
       'productUuid' => 'product_uuid',
       'supplierNumber' => 'supplier_number',
       'isMain' => 'is_main',
       'sales' => 'sales',
       'active' => 'active',
       'stock' => 'stock',
       'minStock' => 'min_stock',
       'weight' => 'weight',
       'position' => 'position',
       'width' => 'width',
       'height' => 'height',
       'length' => 'length',
       'ean' => 'ean',
       'unitUuid' => 'unit_uuid',
       'purchaseSteps' => 'purchase_steps',
       'maxPurchase' => 'max_purchase',
       'minPurchase' => 'min_purchase',
       'purchaseUnit' => 'purchase_unit',
       'referenceUnit' => 'reference_unit',
       'releaseDate' => 'release_date',
       'shippingFree' => 'shipping_free',
       'purchasePrice' => 'purchase_price',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
       'additionalText' => 'translation.additional_text',
       'packUnit' => 'translation.pack_unit',
    ];

    /**
     * @var UnitBasicFactory
     */
    protected $unitFactory;

    /**
     * @var ProductDetailPriceBasicFactory
     */
    protected $productDetailPriceFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        UnitBasicFactory $unitFactory,
        ProductDetailPriceBasicFactory $productDetailPriceFactory
    ) {
        parent::__construct($connection, $registry);
        $this->unitFactory = $unitFactory;
        $this->productDetailPriceFactory = $productDetailPriceFactory;
    }

    public function hydrate(
        array $data,
        ProductDetailBasicStruct $productDetail,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductDetailBasicStruct {
        $productDetail->setUuid((string) $data[$selection->getField('uuid')]);
        $productDetail->setProductUuid((string) $data[$selection->getField('productUuid')]);
        $productDetail->setSupplierNumber(isset($data[$selection->getField('supplierNumber')]) ? (string) $data[$selection->getField('supplierNumber')] : null);
        $productDetail->setIsMain((bool) $data[$selection->getField('isMain')]);
        $productDetail->setSales((int) $data[$selection->getField('sales')]);
        $productDetail->setActive((bool) $data[$selection->getField('active')]);
        $productDetail->setStock((int) $data[$selection->getField('stock')]);
        $productDetail->setMinStock(isset($data[$selection->getField('minStock')]) ? (int) $data[$selection->getField('minStock')] : null);
        $productDetail->setWeight(isset($data[$selection->getField('weight')]) ? (float) $data[$selection->getField('weight')] : null);
        $productDetail->setPosition((int) $data[$selection->getField('position')]);
        $productDetail->setWidth(isset($data[$selection->getField('width')]) ? (float) $data[$selection->getField('width')] : null);
        $productDetail->setHeight(isset($data[$selection->getField('height')]) ? (float) $data[$selection->getField('height')] : null);
        $productDetail->setLength(isset($data[$selection->getField('length')]) ? (float) $data[$selection->getField('length')] : null);
        $productDetail->setEan(isset($data[$selection->getField('ean')]) ? (string) $data[$selection->getField('ean')] : null);
        $productDetail->setUnitUuid(isset($data[$selection->getField('unitUuid')]) ? (string) $data[$selection->getField('unitUuid')] : null);
        $productDetail->setPurchaseSteps(isset($data[$selection->getField('purchaseSteps')]) ? (int) $data[$selection->getField('purchaseSteps')] : null);
        $productDetail->setMaxPurchase(isset($data[$selection->getField('maxPurchase')]) ? (int) $data[$selection->getField('maxPurchase')] : null);
        $productDetail->setMinPurchase((int) $data[$selection->getField('minPurchase')]);
        $productDetail->setPurchaseUnit(isset($data[$selection->getField('purchaseUnit')]) ? (float) $data[$selection->getField('purchaseUnit')] : null);
        $productDetail->setReferenceUnit(isset($data[$selection->getField('referenceUnit')]) ? (float) $data[$selection->getField('referenceUnit')] : null);
        $productDetail->setReleaseDate(isset($data[$selection->getField('releaseDate')]) ? new \DateTime($data[$selection->getField('releaseDate')]) : null);
        $productDetail->setShippingFree((bool) $data[$selection->getField('shippingFree')]);
        $productDetail->setPurchasePrice((float) $data[$selection->getField('purchasePrice')]);
        $productDetail->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $productDetail->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $productDetail->setAdditionalText(isset($data[$selection->getField('additionalText')]) ? (string) $data[$selection->getField('additionalText')] : null);
        $productDetail->setPackUnit(isset($data[$selection->getField('packUnit')]) ? (string) $data[$selection->getField('packUnit')] : null);
        $unit = $selection->filter('unit');
        if ($unit && !empty($data[$unit->getField('uuid')])) {
            $productDetail->setUnit(
                $this->unitFactory->hydrate($data, new UnitBasicStruct(), $unit, $context)
            );
        }

        /** @var $extension ProductDetailExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($productDetail, $data, $selection, $context);
        }

        return $productDetail;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        $fields['unit'] = $this->unitFactory->getFields();

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        $this->joinUnit($selection, $query, $context);
        $this->joinPrices($selection, $query, $context);
        $this->joinTranslation($selection, $query, $context);

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());
        $fields['unit'] = $this->unitFactory->getAllFields();
        $fields['prices'] = $this->productDetailPriceFactory->getAllFields();

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
            'product_detail_price',
            $prices->getRootEscaped(),
            sprintf('%s.uuid = %s.product_detail_uuid', $selection->getRootEscaped(), $prices->getRootEscaped())
        );

        $this->productDetailPriceFactory->joinDependencies($prices, $query, $context);

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
            'product_detail_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.product_detail_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
