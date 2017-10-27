<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\DetailReaderInterface;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\TermsQuery;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\PriceGroup\Factory\PriceGroupDetailFactory;
use Shopware\PriceGroup\Struct\PriceGroupDetailCollection;
use Shopware\PriceGroup\Struct\PriceGroupDetailStruct;
use Shopware\PriceGroupDiscount\Searcher\PriceGroupDiscountSearcher;
use Shopware\PriceGroupDiscount\Searcher\PriceGroupDiscountSearchResult;

class PriceGroupDetailReader implements DetailReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var PriceGroupDetailFactory
     */
    private $factory;

    /**
     * @var PriceGroupDiscountSearcher
     */
    private $priceGroupDiscountSearcher;

    public function __construct(
        PriceGroupDetailFactory $factory,
        PriceGroupDiscountSearcher $priceGroupDiscountSearcher
    ) {
        $this->factory = $factory;
        $this->priceGroupDiscountSearcher = $priceGroupDiscountSearcher;
    }

    public function readDetail(array $uuids, TranslationContext $context): PriceGroupDetailCollection
    {
        if (empty($uuids)) {
            return new PriceGroupDetailCollection();
        }

        $priceGroupsCollection = $this->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('price_group_discount.priceGroupUuid', $uuids));
        /** @var PriceGroupDiscountSearchResult $discounts */
        $discounts = $this->priceGroupDiscountSearcher->search($criteria, $context);

        /** @var PriceGroupDetailStruct $priceGroup */
        foreach ($priceGroupsCollection as $priceGroup) {
            $priceGroup->setDiscounts($discounts->filterByPriceGroupUuid($priceGroup->getUuid()));
        }

        return $priceGroupsCollection;
    }

    private function read(array $uuids, TranslationContext $context): PriceGroupDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('price_group.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new PriceGroupDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new PriceGroupDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
