<?php declare(strict_types=1);

namespace Shopware\Category\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Category\Factory\CategoryDetailFactory;
use Shopware\Category\Struct\CategoryDetailCollection;
use Shopware\Category\Struct\CategoryDetailStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Reader\CustomerGroupBasicReader;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Product\Reader\ProductBasicReader;

class CategoryDetailReader
{
    use SortArrayByKeysTrait;

    /**
     * @var CategoryDetailFactory
     */
    private $factory;

    /**
     * @var ProductBasicReader
     */
    private $productBasicReader;

    /**
     * @var CustomerGroupBasicReader
     */
    private $customerGroupBasicReader;

    public function __construct(
        CategoryDetailFactory $factory,
        ProductBasicReader $productBasicReader,
        CustomerGroupBasicReader $customerGroupBasicReader
    ) {
        $this->factory = $factory;
        $this->productBasicReader = $productBasicReader;
        $this->customerGroupBasicReader = $customerGroupBasicReader;
    }

    public function readDetail(array $uuids, TranslationContext $context): CategoryDetailCollection
    {
        if (empty($uuids)) {
            return new CategoryDetailCollection();
        }

        $categoriesCollection = $this->read($uuids, $context);

        $products = $this->productBasicReader->readBasic($categoriesCollection->getProductUuids(), $context);

        $blockedCustomerGroups = $this->customerGroupBasicReader->readBasic($categoriesCollection->getBlockedCustomerGroupsUuids(), $context);

        /** @var CategoryDetailStruct $category */
        foreach ($categoriesCollection as $category) {
            $category->setProducts($products->getList($category->getProductUuids()));
            $category->setBlockedCustomerGroups($blockedCustomerGroups->getList($category->getBlockedCustomerGroupsUuids()));
        }

        return $categoriesCollection;
    }

    private function read(array $uuids, TranslationContext $context): CategoryDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('category.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new CategoryDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new CategoryDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
