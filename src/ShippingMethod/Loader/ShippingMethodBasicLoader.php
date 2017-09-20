<?php

namespace Shopware\ShippingMethod\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ShippingMethod\Factory\ShippingMethodBasicFactory;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicCollection;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;

class ShippingMethodBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ShippingMethodBasicFactory
     */
    private $factory;

    public function __construct(
        ShippingMethodBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): ShippingMethodBasicCollection
    {
        if (empty($uuids)) {
            return new ShippingMethodBasicCollection();
        }

        $shippingMethodsCollection = $this->read($uuids, $context);

        return $shippingMethodsCollection;
    }

    private function read(array $uuids, TranslationContext $context): ShippingMethodBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('shipping_method.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ShippingMethodBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ShippingMethodBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
