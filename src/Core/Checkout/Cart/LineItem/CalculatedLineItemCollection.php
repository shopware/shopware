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

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPriceCollection;
use Shopware\Core\Framework\Struct\Collection;

class CalculatedLineItemCollection extends Collection
{
    /**
     * @var CalculatedLineItemInterface[]
     */
    protected $elements = [];

    public function add(CalculatedLineItemInterface $lineItem): void
    {
        $key = $this->getKey($lineItem);
        $this->elements[$key] = $lineItem;
    }

    public function remove(string $identifier): void
    {
        parent::doRemoveByKey($identifier);
    }

    public function removeElement(CalculatedLineItemInterface $lineItem): void
    {
        parent::doRemoveByKey($this->getKey($lineItem));
    }

    public function exists(CalculatedLineItemInterface $lineItem): bool
    {
        return parent::has($this->getKey($lineItem));
    }

    public function get(string $identifier): ? CalculatedLineItemInterface
    {
        if ($this->has($identifier)) {
            return $this->elements[$identifier];
        }

        return null;
    }

    public function getIdentifiers(): array
    {
        return $this->getKeys();
    }

    public function getPrices(): CalculatedPriceCollection
    {
        return $this->collectPricesOfLineItems($this);
    }

    public function filterGoods(): self
    {
        return $this->filterInstance(GoodsInterface::class);
    }

    public function current(): CalculatedLineItemInterface
    {
        return parent::current();
    }

    /**
     * Removes the nested line item structure and returns an array containing all line items as root level
     *
     * Result returned as flat array to avoid identifier collision of nested elements of the same type and id,
     * but with different meta information.
     *
     * @return array
     */
    public function getFlatElements(): array
    {
        $flat = [];

        foreach ($this->elements as $element) {
            $flat[] = $element;

            if (!$element instanceof NestedInterface) {
                continue;
            }

            $children = $element->getChildren()->getFlatElements();
            foreach ($children as $child) {
                $flat[] = $child;
            }
        }

        return $flat;
    }

    protected function getKey(CalculatedLineItemInterface $element): string
    {
        return $element->getIdentifier();
    }

    private function collectPricesOfLineItems(CalculatedLineItemCollection $lineItems): CalculatedPriceCollection
    {
        $prices = new CalculatedPriceCollection();
        foreach ($lineItems as $element) {
            $prices->add($element->getPrice());

            if (!$element instanceof NestedInterface) {
                continue;
            }
            if (!$element->considerChildrenPrices()) {
                continue;
            }
            if ($element->getChildren()->count() <= 0) {
                continue;
            }
            $nested = $this->collectPricesOfLineItems($element->getChildren());
            $prices = $prices->merge($nested);
        }

        return $prices;
    }
}
