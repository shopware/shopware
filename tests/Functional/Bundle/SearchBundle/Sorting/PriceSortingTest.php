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

namespace Shopware\Tests\Functional\Bundle\SearchBundle\Sorting;

use Shopware\Bundle\SearchBundle\Sorting\PriceSorting;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class PriceSortingTest extends TestCase
{
    public function testCurrentCustomerGroupPriceSorting()
    {
        $sorting = new PriceSorting();
        $context = $this->getPriceContext(true, 0);

        $customerGroup = $context->getCurrentCustomerGroup();
        $fallback = $context->getFallbackCustomerGroup();

        $this->search(
            [
                'first' => [$customerGroup->getKey() => 20, $fallback->getKey() => 1],
                'second' => [$customerGroup->getKey() => 10, $fallback->getKey() => 1],
                'third' => [$customerGroup->getKey() => 12, $fallback->getKey() => 1],
                'fourth' => [$customerGroup->getKey() => 14, $fallback->getKey() => 1],
            ],
            ['second', 'third', 'fourth', 'first'],
            null,
            [],
            [],
            [$sorting],
            $context
        );
    }

    public function testFallbackCustomerGroupPriceSorting()
    {
        $sorting = new PriceSorting();
        $context = $this->getPriceContext(true, 0);

        $fallback = $context->getFallbackCustomerGroup();

        $this->search(
            [
                'first' => [$fallback->getKey() => 20],
                'second' => [$fallback->getKey() => 10],
                'third' => [$fallback->getKey() => 12],
                'fourth' => [$fallback->getKey() => 14],
            ],
            ['second', 'third', 'fourth', 'first'],
            null,
            [],
            [],
            [$sorting],
            $context
        );
    }

    /**
     * @group skipElasticSearch
     */
    public function testFallbackAndCurrentCustomerGroupPriceSorting()
    {
        $sorting = new PriceSorting();
        $context = $this->getPriceContext(true, 0);

        $customerGroup = $context->getCurrentCustomerGroup();
        $fallback = $context->getFallbackCustomerGroup();

        $this->search(
            [
                'first' => [$customerGroup->getKey() => 20, $fallback->getKey() => 1],
                'second' => [$fallback->getKey() => 10],
                'third' => [$fallback->getKey() => 12],
                'fourth' => [$customerGroup->getKey() => 14, $fallback->getKey() => 1],
            ],
            ['second', 'third', 'fourth', 'first'],
            null,
            [],
            [],
            [$sorting],
            $context
        );
    }

    /**
     * @group skipElasticSearch
     */
    public function testCustomerGroupDiscount()
    {
        $sorting = new PriceSorting();
        $context = $this->getPriceContext(true, 10);

        $customerGroup = $context->getCurrentCustomerGroup();
        $fallback = $context->getFallbackCustomerGroup();

        $this->search(
            [
                'first' => [$customerGroup->getKey() => 40, $fallback->getKey() => 1],
                'second' => [$fallback->getKey() => 10],
                'third' => [$fallback->getKey() => 20],
                'fourth' => [$customerGroup->getKey() => 30, $fallback->getKey() => 1],
            ],
            ['second', 'third', 'fourth', 'first'],
            null,
            [],
            [],
            [$sorting],
            $context
        );
    }

    protected function getPriceContext($displayGross, $discount = null)
    {
        $context = parent::getContext();

        $data = ['key' => 'BAK', 'tax' => $displayGross];

        $context->setFallbackCustomerGroup(
            $this->converter->convertCustomerGroup($this->helper->createCustomerGroup($data))
        );

        $context->getCurrentCustomerGroup()->setDisplayGrossPrices($displayGross);
        $context->getCurrentCustomerGroup()->setUseDiscount(($discount !== null));
        $context->getCurrentCustomerGroup()->setPercentageDiscount($discount);

        return $context;
    }

    /**
     * @param $number
     * @param \Shopware\Context\Struct\ShopContext                        $context
     * @param \Shopware\Models\Category\Category $category
     * @param array                              $prices
     *
     * @return array
     */
    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $prices = []
    ) {
        $product = parent::getProduct($number, $context, $category);

        if (!empty($prices)) {
            $product['mainDetail']['prices'] = [];

            foreach ($prices as $key => $price) {
                $product['mainDetail']['prices'] = array_merge(
                    $product['mainDetail']['prices'],
                    $this->helper->getGraduatedPrices($key, $price)
                );
            }
        }

        return $product;
    }

    protected function search(
        $products,
        $expectedNumbers,
        $category = null,
        $conditions = [],
        $facets = [],
        $sortings = [],
        $context = null,
        array $configs = []
    ) {
        $result = parent::search(
            $products,
            $expectedNumbers,
            $category,
            $conditions,
            $facets,
            $sortings,
            $context
        );

        $this->assertSearchResultSorting($result, $expectedNumbers);

        return $result;
    }
}
