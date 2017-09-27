<?php declare(strict_types=1);

namespace Shopware\Shop\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Loader\CurrencyBasicLoader;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Shop\Factory\ShopDetailFactory;
use Shopware\Shop\Struct\ShopDetailCollection;
use Shopware\Shop\Struct\ShopDetailStruct;

class ShopDetailLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ShopDetailFactory
     */
    private $factory;

    /**
     * @var CurrencyBasicLoader
     */
    private $currencyBasicLoader;

    public function __construct(
        ShopDetailFactory $factory,
        CurrencyBasicLoader $currencyBasicLoader
    ) {
        $this->factory = $factory;
        $this->currencyBasicLoader = $currencyBasicLoader;
    }

    public function load(array $uuids, TranslationContext $context): ShopDetailCollection
    {
        if (empty($uuids)) {
            return new ShopDetailCollection();
        }

        $shopsCollection = $this->read($uuids, $context);

        $availableCurrencies = $this->currencyBasicLoader->load($shopsCollection->getAvailableCurrencyUuids(), $context);

        /** @var ShopDetailStruct $shop */
        foreach ($shopsCollection as $shop) {
            $shop->setAvailableCurrencies($availableCurrencies->getList($shop->getAvailableCurrencyUuids()));
        }

        return $shopsCollection;
    }

    private function read(array $uuids, TranslationContext $context): ShopDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('shop.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ShopDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ShopDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
