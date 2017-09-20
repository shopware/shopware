<?php

namespace Shopware\ProductStream\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ProductStream\Factory\ProductStreamBasicFactory;
use Shopware\ProductStream\Struct\ProductStreamBasicCollection;
use Shopware\ProductStream\Struct\ProductStreamBasicStruct;

class ProductStreamBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductStreamBasicFactory
     */
    private $factory;

    public function __construct(
        ProductStreamBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): ProductStreamBasicCollection
    {
        if (empty($uuids)) {
            return new ProductStreamBasicCollection();
        }

        $productStreamsCollection = $this->read($uuids, $context);

        return $productStreamsCollection;
    }

    private function read(array $uuids, TranslationContext $context): ProductStreamBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product_stream.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductStreamBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductStreamBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
