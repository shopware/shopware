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

use Shopware\Cart\Price\PriceDefinition;
use Shopware\Cart\Price\PriceDefinitionCollection;
use Shopware\Framework\Struct\Collection;

class ProductPriceCollection extends Collection
{
    /**
     * @var PriceDefinitionCollection[]
     */
    protected $elements = [];

    /**
     * @param PriceDefinitionCollection[] $elements
     */
    public function fill(array $elements): void
    {
        foreach ($elements as $key => $element) {
            $this->add($key, $element);
        }
    }

    public function add(string $number, PriceDefinitionCollection $prices): void
    {
        $this->elements[$number] = $prices;
    }

    public function get(string $number): ? PriceDefinitionCollection
    {
        return $this->elements[$number];
    }

    public function getQuantityPrice(string $number, int $quantity): ? PriceDefinition
    {
        if (!$this->has($number)) {
            return null;
        }

        $definitions = $this->get($number);
        if (!$definitions) {
            return null;
        }

        $definitions = $definitions->getElements();

        usort(
            $definitions,
            function (PriceDefinition $a, PriceDefinition $b) {
                return $a->getQuantity() < $b->getQuantity();
            }
        );

        /** @var PriceDefinitionCollection $definitions */
        foreach ($definitions as $definition) {
            /** @var PriceDefinition $definition */
            if ($definition->getQuantity() > $quantity) {
                continue;
            }

            return $definition;
        }

        return null;
    }
}
