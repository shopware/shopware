<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ProductManufacturer\Factory\ProductManufacturerBasicFactory;
use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicCollection;
use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicStruct;

class ProductManufacturerBasicReader
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductManufacturerBasicFactory
     */
    private $factory;

    public function __construct(
        ProductManufacturerBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductManufacturerBasicCollection
    {
        if (empty($uuids)) {
            return new ProductManufacturerBasicCollection();
        }

        $productManufacturersCollection = $this->read($uuids, $context);

        return $productManufacturersCollection;
    }

    private function read(array $uuids, TranslationContext $context): ProductManufacturerBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product_manufacturer.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductManufacturerBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductManufacturerBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
