<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ProductMedia\Factory\ProductMediaBasicFactory;
use Shopware\ProductMedia\Struct\ProductMediaBasicCollection;
use Shopware\ProductMedia\Struct\ProductMediaBasicStruct;

class ProductMediaBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductMediaBasicFactory
     */
    private $factory;

    public function __construct(
        ProductMediaBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductMediaBasicCollection
    {
        if (empty($uuids)) {
            return new ProductMediaBasicCollection();
        }

        $productMediasCollection = $this->read($uuids, $context);

        return $productMediasCollection;
    }

    private function read(array $uuids, TranslationContext $context): ProductMediaBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product_media.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductMediaBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductMediaBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
