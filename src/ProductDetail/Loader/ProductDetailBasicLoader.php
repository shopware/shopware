<?php

namespace Shopware\ProductDetail\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ProductDetail\Factory\ProductDetailBasicFactory;
use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;
use Shopware\ProductDetail\Struct\ProductDetailBasicStruct;

class ProductDetailBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductDetailBasicFactory
     */
    private $factory;

    public function __construct(
        ProductDetailBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): ProductDetailBasicCollection
    {
        if (empty($uuids)) {
            return new ProductDetailBasicCollection();
        }

        $productDetailsCollection = $this->read($uuids, $context);

        return $productDetailsCollection;
    }

    private function read(array $uuids, TranslationContext $context): ProductDetailBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product_detail.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductDetailBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductDetailBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
