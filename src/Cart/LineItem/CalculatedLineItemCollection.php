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

namespace Shopware\Cart\LineItem;

use Shopware\Cart\Price\Struct\PriceCollection;
use Shopware\Framework\Struct\Collection;

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

    public function getPrices(): PriceCollection
    {
        return new PriceCollection(
            array_map(
                function (CalculatedLineItemInterface $item) {
                    return $item->getPrice();
                },
                $this->elements
            )
        );
    }

    public function filterGoods(): CalculatedLineItemCollection
    {
        return $this->filterInstance(GoodsInterface::class);
    }

    protected function getKey(CalculatedLineItemInterface $element): string
    {
        return $element->getIdentifier();
    }

    public function current(): CalculatedLineItemInterface
    {
        return parent::current();
    }
}
