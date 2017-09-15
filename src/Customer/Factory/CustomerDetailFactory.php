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

namespace Shopware\Customer\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\Customer\Struct\CustomerDetailStruct;
use Shopware\CustomerAddress\Factory\CustomerAddressBasicFactory;
use Shopware\CustomerGroup\Factory\CustomerGroupBasicFactory;
use Shopware\PaymentMethod\Factory\PaymentMethodBasicFactory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\Shop\Factory\ShopBasicFactory;
use Shopware\Shop\Struct\ShopBasicStruct;

class CustomerDetailFactory extends CustomerBasicFactory
{
    /**
     * @var CustomerAddressBasicFactory
     */
    protected $customerAddressFactory;

    /**
     * @var ShopBasicFactory
     */
    protected $shopFactory;

    public function __construct(
        Connection $connection,
        array $extensions,
        CustomerAddressBasicFactory $customerAddressFactory,
        ShopBasicFactory $shopFactory,
        CustomerGroupBasicFactory $customerGroupFactory,
        PaymentMethodBasicFactory $paymentMethodFactory
    ) {
        parent::__construct($connection, $extensions, $customerGroupFactory, $customerAddressFactory, $paymentMethodFactory);
        $this->customerAddressFactory = $customerAddressFactory;
        $this->shopFactory = $shopFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());
        $fields['shop'] = $this->shopFactory->getFields();

        return $fields;
    }

    public function hydrate(
        array $data,
        CustomerBasicStruct $customer,
        QuerySelection $selection,
        TranslationContext $context
    ): CustomerBasicStruct {
        /** @var CustomerDetailStruct $customer */
        $customer = parent::hydrate($data, $customer, $selection, $context);
        $shop = $selection->filter('shop');
        if ($shop && !empty($data[$shop->getField('uuid')])) {
            $customer->setShop(
                $this->shopFactory->hydrate($data, new ShopBasicStruct(), $shop, $context)
            );
        }

        return $customer;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        if ($addresss = $selection->filter('addresss')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'customer_address',
                $addresss->getRootEscaped(),
                sprintf('%s.uuid = %s.customer_uuid', $selection->getRootEscaped(), $addresss->getRootEscaped())
            );

            $this->customerAddressFactory->joinDependencies($addresss, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }

        if ($shop = $selection->filter('shop')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'shop',
                $shop->getRootEscaped(),
                sprintf('%s.uuid = %s.shop_uuid', $shop->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->shopFactory->joinDependencies($shop, $query, $context);
        }
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['addresss'] = $this->customerAddressFactory->getAllFields();
        $fields['shop'] = $this->shopFactory->getAllFields();

        return $fields;
    }

    protected function getExtensionFields(): array
    {
        $fields = parent::getExtensionFields();

        foreach ($this->extensions as $extension) {
            $extensionFields = $extension->getDetailFields();
            foreach ($extensionFields as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }
}
