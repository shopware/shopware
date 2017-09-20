<?php

namespace Shopware\PriceGroupDiscount\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\PriceGroupDiscount\Factory\PriceGroupDiscountBasicFactory;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicCollection;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicStruct;

class PriceGroupDiscountBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var PriceGroupDiscountBasicFactory
     */
    private $factory;

    public function __construct(
        PriceGroupDiscountBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): PriceGroupDiscountBasicCollection
    {
        if (empty($uuids)) {
            return new PriceGroupDiscountBasicCollection();
        }

        $priceGroupDiscountsCollection = $this->read($uuids, $context);

        return $priceGroupDiscountsCollection;
    }

    private function read(array $uuids, TranslationContext $context): PriceGroupDiscountBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('price_group_discount.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new PriceGroupDiscountBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new PriceGroupDiscountBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
