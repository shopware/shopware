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
        $paymentMethods = $this->read($uuids, $context);

        $shops = $this->shopBasicLoader->load($paymentMethods->getShopUuids(), $context);

        $countries = $this->areaCountryBasicLoader->load($paymentMethods->getCountryUuids(), $context);

        /** @var PaymentMethodDetailStruct $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethod->setShops($shops->getList($paymentMethod->getShopUuids()));
            $paymentMethod->setCountries($countries->getList($paymentMethod->getCountryUuids()));
        }

        return $paymentMethods;
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
