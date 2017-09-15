<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

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
        $currencies = $this->read($uuids, $context);

        $shops = $this->shopBasicLoader->load($currencies->getShopUuids(), $context);

        /** @var CurrencyDetailStruct $currency */
        foreach ($currencies as $currency) {
            $currency->setShops($shops->getList($currency->getShopUuids()));
        }

        return $currencies;
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
