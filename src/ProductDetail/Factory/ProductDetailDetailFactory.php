<?php

namespace Shopware\ProductDetail\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductDetail\Struct\ProductDetailBasicStruct;
use Shopware\ProductDetail\Struct\ProductDetailDetailStruct;
use Shopware\ProductPrice\Factory\ProductPriceBasicFactory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\Unit\Factory\UnitBasicFactory;

class ProductDetailDetailFactory extends ProductDetailBasicFactory
{
    /**
     * @var ProductPriceBasicFactory
     */
    protected $productPriceFactory;

    public function __construct(
        Connection $connection,
        array $extensions,
        ProductPriceBasicFactory $productPriceFactory,
        UnitBasicFactory $unitFactory
    ) {
        parent::__construct($connection, $extensions, $unitFactory);
        $this->productPriceFactory = $productPriceFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());

        return $fields;
    }

    public function hydrate(
        array $data,
        ProductDetailBasicStruct $productDetail,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductDetailBasicStruct {
        /** @var ProductDetailDetailStruct $productDetail */
        $productDetail = parent::hydrate($data, $productDetail, $selection, $context);

        return $productDetail;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        if ($prices = $selection->filter('prices')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'product_price',
                $prices->getRootEscaped(),
                sprintf('%s.uuid = %s.product_detail_uuid', $selection->getRootEscaped(), $prices->getRootEscaped())
            );

            $this->productPriceFactory->joinDependencies($prices, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['prices'] = $this->productPriceFactory->getAllFields();

        return $fields;
    }

    protected function getExtensionFields(): array
    {
        $fields = parent::getExtensionFields();

        foreach ($this->extensions as $extension) {
            $extensionFields = $extension->getDetailFields();
            foreach ($extensionFields as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }
}
