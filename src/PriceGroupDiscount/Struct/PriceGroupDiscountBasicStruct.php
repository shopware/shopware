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

namespace Shopware\PriceGroupDiscount\Struct;

use Shopware\Framework\Struct\Struct;

class PriceGroupDiscountBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $priceGroupUuid;

    /**
     * @var string
     */
    protected $customerGroupUuid;

    /**
     * @var float
     */
    protected $percentageDiscount;

    /**
     * @var float
     */
    protected $productCount;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getPriceGroupUuid(): string
    {
        return $this->priceGroupUuid;
    }

    public function setPriceGroupUuid(string $priceGroupUuid): void
    {
        $this->priceGroupUuid = $priceGroupUuid;
    }

    public function getCustomerGroupUuid(): string
    {
        return $this->customerGroupUuid;
    }

    public function setCustomerGroupUuid(string $customerGroupUuid): void
    {
        $this->customerGroupUuid = $customerGroupUuid;
    }

    public function getPercentageDiscount(): float
    {
        return $this->percentageDiscount;
    }

    public function setPercentageDiscount(float $percentageDiscount): void
    {
        $this->percentageDiscount = $percentageDiscount;
    }

    public function getProductCount(): float
    {
        return $this->productCount;
    }

    public function setProductCount(float $productCount): void
    {
        $this->productCount = $productCount;
    }
}
