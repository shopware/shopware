<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ProductDetailPrice\Factory\ProductDetailPriceBasicFactory;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicCollection;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicStruct;

class ProductDetailPriceBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductDetailPriceBasicFactory
     */
    private $factory;

    public function __construct(
        ProductDetailPriceBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductDetailPriceBasicCollection
    {
        if (empty($uuids)) {
            return new ProductDetailPriceBasicCollection();
        }

        $productDetailPricesCollection = $this->read($uuids, $context);

        return $productDetailPricesCollection;
    }

    private function read(array $uuids, TranslationContext $context): ProductDetailPriceBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product_detail_price.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductDetailPriceBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductDetailPriceBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
