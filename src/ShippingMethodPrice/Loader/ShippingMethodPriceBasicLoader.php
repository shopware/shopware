<?php

namespace Shopware\ShippingMethodPrice\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ShippingMethodPrice\Factory\ShippingMethodPriceBasicFactory;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicCollection;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicStruct;

class ShippingMethodPriceBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ShippingMethodPriceBasicFactory
     */
    private $factory;

    public function __construct(
        ShippingMethodPriceBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): ShippingMethodPriceBasicCollection
    {
        if (empty($uuids)) {
            return new ShippingMethodPriceBasicCollection();
        }

        $shippingMethodPrices = $this->read($uuids, $context);

        return $shippingMethodPrices;
    }

    private function read(array $uuids, TranslationContext $context): ShippingMethodPriceBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('shipping_method_price.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ShippingMethodPriceBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ShippingMethodPriceBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
