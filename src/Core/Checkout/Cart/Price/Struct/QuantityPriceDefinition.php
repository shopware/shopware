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

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Struct\Struct;

class QuantityPriceDefinition extends Struct implements PriceDefinitionInterface
{
    /** @var float */
    protected $price;

    /** @var TaxRuleCollection */
    protected $taxRules;

    /** @var int */
    protected $quantity;

    /**
     * @var bool
     */
    protected $isCalculated;

    public function __construct(
        float $price,
        TaxRuleCollection $taxRules,
        int $quantity = 1,
        bool $isCalculated = false
    ) {
        $this->price = $price;
        $this->taxRules = $taxRules;
        $this->quantity = $quantity;
        $this->isCalculated = $isCalculated;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getTaxRules(): TaxRuleCollection
    {
        return $this->taxRules;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function isCalculated(): bool
    {
        return $this->isCalculated;
    }
}
