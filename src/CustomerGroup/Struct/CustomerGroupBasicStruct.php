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

namespace Shopware\CustomerGroup\Struct;

use Shopware\Framework\Struct\Struct;

class CustomerGroupBasicStruct extends Struct
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $groupKey;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var bool
     */
    protected $displayGrossPrices;

    /**
     * @var bool
     */
    protected $inputGrossPrices;

    /**
     * @var int
     */
    protected $mode;

    /**
     * @var float
     */
    protected $discount;

    /**
     * @var float
     */
    protected $minimumOrderAmount;

    /**
     * @var float
     */
    protected $minimumOrderAmountSurcharge;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getGroupKey(): string
    {
        return $this->groupKey;
    }

    public function setGroupKey(string $groupKey): void
    {
        $this->groupKey = $groupKey;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getDisplayGrossPrices(): bool
    {
        return $this->displayGrossPrices;
    }

    public function setDisplayGrossPrices(bool $displayGrossPrices): void
    {
        $this->displayGrossPrices = $displayGrossPrices;
    }

    public function getInputGrossPrices(): bool
    {
        return $this->inputGrossPrices;
    }

    public function setInputGrossPrices(bool $inputGrossPrices): void
    {
        $this->inputGrossPrices = $inputGrossPrices;
    }

    public function getMode(): int
    {
        return $this->mode;
    }

    public function setMode(int $mode): void
    {
        $this->mode = $mode;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    public function setDiscount(float $discount): void
    {
        $this->discount = $discount;
    }

    public function getMinimumOrderAmount(): float
    {
        return $this->minimumOrderAmount;
    }

    public function setMinimumOrderAmount(float $minimumOrderAmount): void
    {
        $this->minimumOrderAmount = $minimumOrderAmount;
    }

    public function getMinimumOrderAmountSurcharge(): float
    {
        return $this->minimumOrderAmountSurcharge;
    }

    public function setMinimumOrderAmountSurcharge(float $minimumOrderAmountSurcharge): void
    {
        $this->minimumOrderAmountSurcharge = $minimumOrderAmountSurcharge;
    }
}
