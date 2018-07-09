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

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Framework\Struct\Collection;

class LineItemCollection extends Collection
{
    /**
     * @var LineItem[]
     */
    protected $elements = [];

    public function add(LineItem $lineItem): void
    {
        $exists = $this->get($lineItem->getIdentifier());

        if ($exists) {
            $exists->setQuantity($lineItem->getQuantity() + $exists->getQuantity());

            return;
        }

        $this->elements[$this->getKey($lineItem)] = $lineItem;
    }

    public function remove(string $identifier): void
    {
        parent::doRemoveByKey($identifier);
    }

    public function removeElement(LineItem $lineItem): void
    {
        parent::doRemoveByKey($this->getKey($lineItem));
    }

    public function exists(LineItem $lineItem): bool
    {
        return parent::has($this->getKey($lineItem));
    }

    public function get(string $identifier): ? LineItem
    {
        if ($this->has($identifier)) {
            return $this->elements[$identifier];
        }

        return null;
    }

    public function filterType(string $type): self
    {
        return $this->filter(
            function (LineItem $lineItem) use ($type) {
                return $lineItem->getType() === $type;
            }
        );
    }

    public function getPayload(): array
    {
        return $this->map(function (LineItem $lineItem) {
            return $lineItem->getPayload();
        });
    }

    public function getIdentifiers(): array
    {
        return $this->getKeys();
    }

    protected function getKey(LineItem $element): string
    {
        return $element->getIdentifier();
    }

    public function getPrices(): PriceCollection
    {
        return new PriceCollection(
            $this->fmap(function(LineItem $lineItem) {
                return $lineItem->getPrice();
            })
        );
    }
}
