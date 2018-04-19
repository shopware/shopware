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

namespace Shopware\CustomerGroup\Struct;

use Shopware\Framework\Struct\Struct;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CustomerGroup extends Struct
{
    /**
     * Unique identifier
     *
     * @var int
     */
    protected $id;

    /**
     * Alphanumeric unique identifier.
     *
     * @var string
     */
    protected $key;

    /**
     * Name of the customer group
     *
     * @var string
     */
    protected $name;

    /**
     * Defines if the customer group
     * should see gross prices in the store
     * front.
     *
     * @var bool
     */
    protected $displayGrossPrices;

    /**
     * Defines if prices inserted as gross prices
     *
     * @var bool
     */
    protected $insertedGrossPrices;

    /**
     * Defines if the display price
     * already reduces with a global customer
     * group discount
     *
     * @var bool
     */
    protected $useDiscount;

    /**
     * Percentage global discount value
     * for this customer group.
     *
     * @var float
     */
    protected $percentageDiscount;

    /**
     * Minimal order value for the customer group.
     * If this value isn't reached in the basket,
     * the defined surcharge will be added
     * as basket position.
     *
     * @var float
     */
    protected $minimumOrderValue;

    /**
     * Numeric surcharge value for the customer group.
     * This value is used for the additional basket
     * position if the $minimumOrderValue of the
     * customer group isn't reached.
     *
     * @var float
     */
    protected $surcharge;

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param string $key
     */
    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param float $percentageDiscount
     */
    public function setPercentageDiscount($percentageDiscount)
    {
        $this->percentageDiscount = $percentageDiscount;
    }

    /**
     * @return float
     */
    public function getPercentageDiscount(): float
    {
        return $this->percentageDiscount;
    }

    /**
     * @param float $surcharge
     */
    public function setSurcharge($surcharge)
    {
        $this->surcharge = $surcharge;
    }

    /**
     * @return float
     */
    public function getSurcharge(): float
    {
        return $this->surcharge;
    }

    /**
     * @param bool $useDiscount
     */
    public function setUseDiscount($useDiscount)
    {
        $this->useDiscount = $useDiscount;
    }

    /**
     * @return bool
     */
    public function useDiscount(): bool
    {
        return $this->useDiscount;
    }

    /**
     * @param bool $displayGrossPrices
     */
    public function setDisplayGrossPrices($displayGrossPrices)
    {
        $this->displayGrossPrices = $displayGrossPrices;
    }

    /**
     * @return bool
     */
    public function displayGrossPrices(): bool
    {
        return $this->displayGrossPrices;
    }

    /**
     * @return bool
     */
    public function insertedGrossPrices(): bool
    {
        return $this->insertedGrossPrices;
    }

    /**
     * @param bool $insertedGrossPrices
     */
    public function setInsertedGrossPrices($insertedGrossPrices)
    {
        $this->insertedGrossPrices = $insertedGrossPrices;
    }

    /**
     * @return float
     */
    public function getMinimumOrderValue(): float
    {
        return $this->minimumOrderValue;
    }

    /**
     * @param float $minimumOrderValue
     */
    public function setMinimumOrderValue($minimumOrderValue): void
    {
        $this->minimumOrderValue = $minimumOrderValue;
    }
}
