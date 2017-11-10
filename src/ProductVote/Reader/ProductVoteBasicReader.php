<?php declare(strict_types=1);

namespace Shopware\ProductVote\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ProductVote\Factory\ProductVoteBasicFactory;
use Shopware\ProductVote\Struct\ProductVoteBasicCollection;
use Shopware\ProductVote\Struct\ProductVoteBasicStruct;

class ProductVoteBasicReader implements BasicReaderInterface
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

    public function readBasic(array $uuids, TranslationContext $context): ProductVoteBasicCollection
    {
        if (empty($uuids)) {
            return new ProductVoteBasicCollection();
        }

        $productVotesCollection = $this->read($uuids, $context);

        return $productVotesCollection;
    }

    private function read(array $uuids, TranslationContext $context): ProductVoteBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product_vote.uuid IN (:ids)');
        $query->setParameter('ids', $uuids, Connection::PARAM_STR_ARRAY);

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
