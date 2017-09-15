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
     * @var string
     */
    protected $uuid;

    /**
     * @var bool
     */
    protected $displayGross;

    /**
     * @var bool
     */
    protected $inputGross;

    /**
     * @var bool
     */
    protected $hasGlobalDiscount;

    /**
     * @var float|null
     */
    protected $percentageGlobalDiscount;

    /**
     * @var float|null
     */
    protected $minimumOrderAmount;

    /**
     * @var float|null
     */
    protected $minimumOrderAmountSurcharge;

    /**
     * @var string
     */
    protected $name;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getDisplayGross(): bool
    {
        return $this->displayGross;
    }

    public function setDisplayGross(bool $displayGross): void
    {
        $this->displayGross = $displayGross;
    }

    public function getInputGross(): bool
    {
        return $this->inputGross;
    }

    public function setInputGross(bool $inputGross): void
    {
        $this->inputGross = $inputGross;
    }

    public function getHasGlobalDiscount(): bool
    {
        return $this->hasGlobalDiscount;
    }

    public function setHasGlobalDiscount(bool $hasGlobalDiscount): void
    {
        $this->hasGlobalDiscount = $hasGlobalDiscount;
    }

    public function getPercentageGlobalDiscount(): ?float
    {
        return $this->percentageGlobalDiscount;
    }

    public function setPercentageGlobalDiscount(?float $percentageGlobalDiscount): void
    {
        $this->percentageGlobalDiscount = $percentageGlobalDiscount;
    }

    public function getMinimumOrderAmount(): ?float
    {
        return $this->minimumOrderAmount;
    }

    public function setMinimumOrderAmount(?float $minimumOrderAmount): void
    {
        $this->minimumOrderAmount = $minimumOrderAmount;
    }

    public function getMinimumOrderAmountSurcharge(): ?float
    {
        return $this->minimumOrderAmountSurcharge;
    }

    public function setMinimumOrderAmountSurcharge(?float $minimumOrderAmountSurcharge): void
    {
        $this->minimumOrderAmountSurcharge = $minimumOrderAmountSurcharge;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
