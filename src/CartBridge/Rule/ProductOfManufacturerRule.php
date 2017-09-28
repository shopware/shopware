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

namespace Shopware\Cart\Rule;

use Shopware\Cart\Cart\CalculatedCart;
use Shopware\CartBridge\Rule\Data\ProductOfManufacturerRuleData;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class ProductOfManufacturerRule extends Rule
{
    /**
     * @var int[]
     */
    protected $manufacturerIds;

    public function __construct(array $manufacturerIds)
    {
        $this->manufacturerIds = $manufacturerIds;
    }

    /**
     * @return \int[]
     */
    public function getManufacturerIds(): array
    {
        return $this->manufacturerIds;
    }

    public function match(
        CalculatedCart $calculatedCart,
        ShopContext $context,
        StructCollection $collection
    ): Match {
        /** @var ProductOfManufacturerRuleData $data */
        $data = $collection->get(ProductOfManufacturerRuleData::class);

        //no products found for categories? invalid
        if (!$data) {
            return new Match(
                false,
                ['Product of manufacturer rule data not loaded']
            );
        }

        return new Match(
            $data->hasOneManufacturer($this->manufacturerIds),
            ['Product manufacturer not matched']
        );
    }
}
