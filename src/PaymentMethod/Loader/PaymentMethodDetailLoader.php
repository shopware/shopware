<?php

namespace Shopware\PaymentMethod\Loader;

use Doctrine\DBAL\Connection;
use Shopware\AreaCountry\Loader\AreaCountryBasicLoader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\PaymentMethod\Factory\PaymentMethodDetailFactory;
use Shopware\PaymentMethod\Struct\PaymentMethodDetailCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodDetailStruct;
use Shopware\Shop\Loader\ShopBasicLoader;

class PaymentMethodDetailLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var PaymentMethodDetailFactory
     */
    private $factory;

    /**
     * @var ShopBasicLoader
     */
    private $shopBasicLoader;

    /**
     * @var AreaCountryBasicLoader
     */
    private $areaCountryBasicLoader;

    public function __construct(
        PaymentMethodDetailFactory $factory,
        ShopBasicLoader $shopBasicLoader,
        AreaCountryBasicLoader $areaCountryBasicLoader
    ) {
        $this->factory = $factory;
        $this->shopBasicLoader = $shopBasicLoader;
        $this->areaCountryBasicLoader = $areaCountryBasicLoader;
    }

    public function load(array $uuids, TranslationContext $context): PaymentMethodDetailCollection
    {
        if (empty($uuids)) {
            return new PaymentMethodDetailCollection();
        }

        $paymentMethodsCollection = $this->read($uuids, $context);

        $shops = $this->shopBasicLoader->load($paymentMethodsCollection->getShopUuids(), $context);

        $countries = $this->areaCountryBasicLoader->load($paymentMethodsCollection->getCountryUuids(), $context);

        /** @var PaymentMethodDetailStruct $paymentMethod */
        foreach ($paymentMethodsCollection as $paymentMethod) {
            $paymentMethod->setShops($shops->getList($paymentMethod->getShopUuids()));
            $paymentMethod->setCountries($countries->getList($paymentMethod->getCountryUuids()));
        }

        return $paymentMethodsCollection;
    }

    private function read(array $uuids, TranslationContext $context): PaymentMethodDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('payment_method.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new PaymentMethodDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new PaymentMethodDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
