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

namespace Shopware\Cart\Product;

use Shopware\Cart\Error\Error;
use Shopware\Cart\Price\PriceCollection;
use Shopware\Framework\Struct\Collection;

class CalculatedProductCollection extends Collection
{
    /**
     * @var CalculatedProduct[]
     */
    protected $elements = [];

    /**
     * @var array
     */
    protected $errors = [];

    public function addError(Error $error): void
    {
        $this->errors[] = $error;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function add(CalculatedProduct $product): void
    {
        $this->elements[$this->getKey($product)] = $product;
    }

    public function remove(string $identifier): void
    {
        parent::doRemoveByKey($identifier);
    }

    public function removeElement(CalculatedProduct $product): void
    {
        parent::doRemoveByKey($this->getKey($product));
    }

    public function exists(CalculatedProduct $product): bool
    {
        return parent::has($this->getKey($product));
    }

    public function get(string $identifier): ? CalculatedProduct
    {
        if ($this->has($identifier)) {
            return $this->elements[$identifier];
        }

        return null;
    }

    public function getPrices(): PriceCollection
    {
        return new PriceCollection(
            array_map(
                function (CalculatedProduct $item) {
                    return $item->getPrice();
                },
                $this->elements
            )
        );
    }

    protected function getKey(CalculatedProduct $element): string
    {
        return $element->getIdentifier();
    }
}
