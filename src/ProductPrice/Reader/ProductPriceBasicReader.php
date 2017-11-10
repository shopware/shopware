<?php declare(strict_types=1);

namespace Shopware\ProductPrice\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ProductPrice\Factory\ProductPriceBasicFactory;
use Shopware\ProductPrice\Struct\ProductPriceBasicCollection;
use Shopware\ProductPrice\Struct\ProductPriceBasicStruct;

class ProductPriceBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductPriceBasicFactory
     */
    private $factory;

    public function __construct(
        ProductPriceBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductPriceBasicCollection
    {
        if (empty($uuids)) {
            return new ProductPriceBasicCollection();
        }

        $productPricesCollection = $this->read($uuids, $context);

        return $productPricesCollection;
    }

    private function read(array $uuids, TranslationContext $context): ProductPriceBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product_price.uuid IN (:ids)');
        $query->setParameter('ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductPriceBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductPriceBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
