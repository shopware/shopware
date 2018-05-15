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

namespace Shopware\Checkout\Cart\Price\Struct;

use Shopware\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Framework\Struct\Collection;

class PriceDefinitionCollection extends Collection
{
    /**
     * @var PriceDefinition[]
     */
    protected $elements = [];

    public function add(PriceDefinition $price): void
    {
        parent::doAdd($price);
    }

    public function remove(int $key): void
    {
        parent::doRemoveByKey($key);
    }

    public function get(int $key): ? PriceDefinition
    {
        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return null;
    }

    public function getTaxRules(): TaxRuleCollection
    {
        $rules = new TaxRuleCollection([]);
        foreach ($this->elements as $price) {
            $rules = $rules->merge($price->getTaxRules());
        }

        return $rules;
    }

    public function merge(self $definitions): self
    {
        return $this->doMerge($definitions);
    }
}
