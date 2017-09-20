<?php

namespace Shopware\Currency\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Factory\CurrencyDetailFactory;
use Shopware\Currency\Struct\CurrencyDetailCollection;
use Shopware\Currency\Struct\CurrencyDetailStruct;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Shop\Loader\ShopBasicLoader;

class CurrencyDetailLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var CurrencyDetailFactory
     */
    private $factory;

    /**
     * @var ShopBasicLoader
     */
    private $shopBasicLoader;

    public function __construct(
        CurrencyDetailFactory $factory,
        ShopBasicLoader $shopBasicLoader
    ) {
        $this->factory = $factory;
        $this->shopBasicLoader = $shopBasicLoader;
    }

    public function load(array $uuids, TranslationContext $context): CurrencyDetailCollection
    {
        if (empty($uuids)) {
            return new CurrencyDetailCollection();
        }

        $currenciesCollection = $this->read($uuids, $context);

        $shops = $this->shopBasicLoader->load($currenciesCollection->getShopUuids(), $context);

        /** @var CurrencyDetailStruct $currency */
        foreach ($currenciesCollection as $currency) {
            $currency->setShops($shops->getList($currency->getShopUuids()));
        }

        return $currenciesCollection;
    }

    private function read(array $uuids, TranslationContext $context): CurrencyDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('currency.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new CurrencyDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new CurrencyDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
