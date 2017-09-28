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

namespace Shopware\Cart\Voucher;

use Shopware\Cart\Price\PriceDefinition;
use Shopware\Cart\Rule\Rule;

class Voucher
{
    /**
     * @var string
     */
    protected $code;

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var Rule|null
     */
    protected $rule;

    /**
     * @var float
     */
    protected $percentageDiscount;

    /**
     * @var PriceDefinition|null
     */
    protected $price;

    public function __construct(string $code, string $mode, ?float $percentageDiscount, ?PriceDefinition $price, ?Rule $rule)
    {
        $this->code = $code;
        $this->mode = $mode;
        $this->rule = $rule;
        $this->percentageDiscount = $percentageDiscount;
        $this->price = $price;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getRule(): ?Rule
    {
        return $this->rule;
    }

    public function getPercentageDiscount(): ?float
    {
        return $this->percentageDiscount;
    }

    public function getPrice(): ?PriceDefinition
    {
        return $this->price;
    }
}
