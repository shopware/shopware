<?php
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

namespace Shopware\CartBridge\Rule;

use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\Rule\Match;
use Shopware\Cart\Rule\Rule;
use Shopware\CartBridge\Rule\Data\ProductOfCategoriesRuleData;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class ProductOfCategoriesRule extends Rule
{
    /**
     * @var int[]
     */
    protected $categoryIds = [];

    /**
     * @param int[] $categoryIds
     */
    public function __construct(array $categoryIds)
    {
        $this->categoryIds = $categoryIds;
    }

    public function match(
        CalculatedCart $calculatedCart,
        ShopContext $context,
        StructCollection $collection
    ): Match {
        /** @var ProductOfCategoriesRuleData $data */
        $data = $collection->get(ProductOfCategoriesRuleData::class);

        //no products found for categories? invalid
        if (!$data) {
            return new Match(
                false,
                ['Product of categories rule data not loaded']
            );
        }

        return new Match(
            $data->hasCategory($this->categoryIds),
            ['Product category not matched']
        );
    }

    public function getCategoryIds(): array
    {
        return $this->categoryIds;
    }
}
