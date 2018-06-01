<?php
declare(strict_types=1);
/**
 * Shopware\Core 5
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
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Checkout\Cart\Tax\Struct;

use Shopware\Core\Framework\Struct\Collection;

class TaxRuleCollection extends Collection
{
    /**
     * @var TaxRuleInterface[]
     */
    protected $elements = [];

    public function add(TaxRuleInterface $taxRule): void
    {
        $this->elements[$this->getKey($taxRule)] = $taxRule;
    }

    public function remove(float $taxRate): void
    {
        parent::doRemoveByKey((string) $taxRate);
    }

    public function removeElement(TaxRuleInterface $taxRule): void
    {
        parent::doRemoveByKey($this->getKey($taxRule));
    }

    public function exists(TaxRuleInterface $taxRule): bool
    {
        return parent::has($this->getKey($taxRule));
    }

    public function get(float $taxRate): ? TaxRuleInterface
    {
        $key = (string) $taxRate;

        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return null;
    }

    public function merge(self $rules): self
    {
        $new = new self($this->elements);

        $rules->map(
            function (TaxRuleInterface $rule) use ($new) {
                if (!$new->exists($rule)) {
                    $new->add($rule);
                }
            }
        );

        return $new;
    }

    protected function getKey(TaxRuleInterface $element): string
    {
        return (string) $element->getRate();
    }
}
