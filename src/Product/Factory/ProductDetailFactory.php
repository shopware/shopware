<?php

namespace Shopware\Product\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Category\Factory\CategoryBasicFactory;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Factory\CustomerGroupBasicFactory;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\PriceGroup\Factory\PriceGroupBasicFactory;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\Product\Struct\ProductDetailStruct;
use Shopware\ProductDetail\Factory\ProductDetailBasicFactory;
use Shopware\ProductManufacturer\Factory\ProductManufacturerBasicFactory;
use Shopware\ProductVote\Factory\ProductVoteBasicFactory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\SeoUrl\Factory\SeoUrlBasicFactory;
use Shopware\Tax\Factory\TaxBasicFactory;

class ProductDetailFactory extends ProductBasicFactory
{
    /**
     * @var ProductDetailBasicFactory
     */
    protected $productDetailFactory;

    /**
     * @var CategoryBasicFactory
     */
    protected $categoryFactory;

    /**
     * @var ProductVoteBasicFactory
     */
    protected $productVoteFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        ProductDetailBasicFactory $productDetailFactory,
        CategoryBasicFactory $categoryFactory,
        ProductVoteBasicFactory $productVoteFactory,
        ProductManufacturerBasicFactory $productManufacturerFactory,
        TaxBasicFactory $taxFactory,
        SeoUrlBasicFactory $seoUrlFactory,
        PriceGroupBasicFactory $priceGroupFactory,
        CustomerGroupBasicFactory $customerGroupFactory
    ) {
        parent::__construct($connection, $registry, $productManufacturerFactory, $taxFactory, $seoUrlFactory, $priceGroupFactory, $customerGroupFactory);
        $this->productDetailFactory = $productDetailFactory;
        $this->categoryFactory = $categoryFactory;
        $this->productVoteFactory = $productVoteFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());
        $fields['_sub_select_category_uuids'] = '_sub_select_category_uuids';
        $fields['_sub_select_categoryTree_uuids'] = '_sub_select_categoryTree_uuids';

        return $fields;
    }

    public function hydrate(
        array $data,
        ProductBasicStruct $product,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductBasicStruct {
        /** @var ProductDetailStruct $product */
        $product = parent::hydrate($data, $product, $selection, $context);
        if ($selection->hasField('_sub_select_category_uuids')) {
            $uuids = explode('|', $data[$selection->getField('_sub_select_category_uuids')]);
            $product->setCategoryUuids(array_values(array_filter($uuids)));
        }

        if ($selection->hasField('_sub_select_categoryTree_uuids')) {
            $uuids = explode('|', $data[$selection->getField('_sub_select_categoryTree_uuids')]);
            $product->setCategoryTreeUuids(array_values(array_filter($uuids)));
        }

        return $product;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        if ($details = $selection->filter('details')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'product_detail',
                $details->getRootEscaped(),
                sprintf('%s.uuid = %s.product_uuid', $selection->getRootEscaped(), $details->getRootEscaped())
            );

            $this->productDetailFactory->joinDependencies($details, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }

        if ($categories = $selection->filter('categories')) {
            $mapping = QuerySelection::escape($categories->getRoot() . '.mapping');

            $query->leftJoin(
                $selection->getRootEscaped(),
                'product_category',
                $mapping,
                sprintf('%s.uuid = %s.product_uuid', $selection->getRootEscaped(), $mapping)
            );
            $query->leftJoin(
                $mapping,
                'category',
                $categories->getRootEscaped(),
                sprintf('%s.category_uuid = %s.uuid', $mapping, $categories->getRootEscaped())
            );

            $this->categoryFactory->joinDependencies($categories, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }

        if ($selection->hasField('_sub_select_category_uuids')) {
            $query->addSelect('
                (
                    SELECT GROUP_CONCAT(mapping.category_uuid SEPARATOR \'|\')
                    FROM product_category mapping
                    WHERE mapping.product_uuid = ' . $selection->getRootEscaped() . '.uuid
                ) as ' . QuerySelection::escape($selection->getField('_sub_select_category_uuids'))
            );
        }

        if ($categoryTree = $selection->filter('categoryTree')) {
            $mapping = QuerySelection::escape($categoryTree->getRoot() . '.mapping');

            $query->leftJoin(
                $selection->getRootEscaped(),
                'product_category_ro',
                $mapping,
                sprintf('%s.uuid = %s.product_uuid', $selection->getRootEscaped(), $mapping)
            );
            $query->leftJoin(
                $mapping,
                'category',
                $categoryTree->getRootEscaped(),
                sprintf('%s.category_uuid = %s.uuid', $mapping, $categoryTree->getRootEscaped())
            );

            $this->categoryFactory->joinDependencies($categoryTree, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }

        if ($selection->hasField('_sub_select_categoryTree_uuids')) {
            $query->addSelect('
                (
                    SELECT GROUP_CONCAT(mapping.category_uuid SEPARATOR \'|\')
                    FROM product_category_ro mapping
                    WHERE mapping.product_uuid = ' . $selection->getRootEscaped() . '.uuid
                ) as ' . QuerySelection::escape($selection->getField('_sub_select_categoryTree_uuids'))
            );
        }

        if ($votes = $selection->filter('votes')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'product_vote',
                $votes->getRootEscaped(),
                sprintf('%s.uuid = %s.product_uuid', $selection->getRootEscaped(), $votes->getRootEscaped())
            );

            $this->productVoteFactory->joinDependencies($votes, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['details'] = $this->productDetailFactory->getAllFields();
        $fields['categories'] = $this->categoryFactory->getAllFields();
        $fields['categoryTree'] = $this->categoryFactory->getAllFields();
        $fields['votes'] = $this->productVoteFactory->getAllFields();

        return $fields;
    }

    protected function getExtensionFields(): array
    {
        $fields = parent::getExtensionFields();

        foreach ($this->getExtensions() as $extension) {
            $extensionFields = $extension->getDetailFields();
            foreach ($extensionFields as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }
}
