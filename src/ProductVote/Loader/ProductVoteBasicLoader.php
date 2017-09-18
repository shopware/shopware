<?php

namespace Shopware\ProductVote\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ProductVote\Factory\ProductVoteBasicFactory;
use Shopware\ProductVote\Struct\ProductVoteBasicCollection;
use Shopware\ProductVote\Struct\ProductVoteBasicStruct;

class ProductVoteBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductVoteBasicFactory
     */
    private $factory;

    public function __construct(
        ProductVoteBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): ProductVoteBasicCollection
    {
        if (empty($uuids)) {
            return new ProductVoteBasicCollection();
        }

        $productVotes = $this->read($uuids, $context);

        return $productVotes;
    }

    private function read(array $uuids, TranslationContext $context): ProductVoteBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product_vote.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductVoteBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductVoteBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
