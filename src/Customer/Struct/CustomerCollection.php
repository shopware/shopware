<?php
declare(strict_types=1);
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

namespace Shopware\Customer\Struct;

use Shopware\Framework\Struct\Collection;

class CustomerCollection extends Collection
{
    /**
     * @var Customer[]
     */
    protected $elements = [];

    public function add(Customer $customer): void
    {
        $key = $this->getKey($customer);
        $this->elements[$key] = $customer;
    }

    public function remove(int $id): void
    {
        parent::doRemoveByKey($id);
    }

    public function removeElement(Customer $customer): void
    {
        parent::doRemoveByKey($this->getKey($customer));
    }

    public function exists(Customer $customer): bool
    {
        return parent::has($this->getKey($customer));
    }

    public function get(int $id): ? Customer
    {
        if ($this->has($id)) {
            return $this->elements[$id];
        }

        return null;
    }

    public function getPaymentIds(): array
    {
        $ids = [];
        foreach ($this->elements as $customer) {
            $ids[] = $customer->getLastPaymentMethodId();
            $ids[] = $customer->getPresetPaymentMethodId();
        }

        return $ids;
    }

    public function getShopIds(): array
    {
        $ids = [];
        foreach ($this->elements as $customer) {
            $ids[] = $customer->getAssignedShopId();
            $ids[] = $customer->getAssignedLanguageShopId();
        }

        return $ids;
    }

    public function getAddressIds(): array
    {
        $ids = [];
        foreach ($this->elements as $customer) {
            $ids[] = $customer->getDefaultShippingAddressId();
            $ids[] = $customer->getDefaultBillingAddressId();
        }

        return $ids;
    }

    protected function getKey(Customer $element): int
    {
        return $element->getId();
    }
}
