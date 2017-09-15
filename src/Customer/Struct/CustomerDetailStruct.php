<?php declare(strict_types=1);
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

use Shopware\CustomerAddress\Struct\CustomerAddressBasicCollection;
use Shopware\Shop\Struct\ShopBasicStruct;

class CustomerDetailStruct extends CustomerBasicStruct
{
    /**
     * @var string[]
     */
    protected $addressUuids = [];

    /**
     * @var CustomerAddressBasicCollection
     */
    protected $addresss;

    /**
     * @var ShopBasicStruct
     */
    protected $shop;

    public function __construct()
    {
        $this->addresss = new CustomerAddressBasicCollection();
    }

    public function getAddressUuids(): array
    {
        return $this->addressUuids;
    }

    public function setAddressUuids(array $addressUuids): void
    {
        $this->addressUuids = $addressUuids;
    }

    public function getAddresss(): CustomerAddressBasicCollection
    {
        return $this->addresss;
    }

    public function setAddresss(CustomerAddressBasicCollection $addresss): void
    {
        $this->addresss = $addresss;
    }

    public function getShop(): ShopBasicStruct
    {
        return $this->shop;
    }

    public function setShop(ShopBasicStruct $shop): void
    {
        $this->shop = $shop;
    }
}
