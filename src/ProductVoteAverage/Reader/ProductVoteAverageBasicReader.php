<?php declare(strict_types=1);

namespace Shopware\ProductVoteAverage\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ProductVoteAverage\Factory\ProductVoteAverageBasicFactory;
use Shopware\ProductVoteAverage\Struct\ProductVoteAverageBasicCollection;
use Shopware\ProductVoteAverage\Struct\ProductVoteAverageBasicStruct;

class ProductVoteAverageBasicReader
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductVoteAverageBasicFactory
     */
    private $factory;

    public function __construct(
        ProductVoteAverageBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): ProductVoteAverageBasicCollection
    {
        if (empty($uuids)) {
            return new ProductVoteAverageBasicCollection();
        }

        $productVoteAveragesCollection = $this->read($uuids, $context);

        return $productVoteAveragesCollection;
    }

    private function read(array $uuids, TranslationContext $context): ProductVoteAverageBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product_vote_average_ro.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductVoteAverageBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductVoteAverageBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
