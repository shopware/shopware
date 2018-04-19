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

namespace Shopware\Tests\Functional\Bundle\SearchBundle\Facet;

use Shopware\Bundle\SearchBundle\Facet\PriceFacet;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class PriceFacetTest extends TestCase
{
    public function testFacetWithCurrentCustomerGroupPrices()
    {
        $context = $this->getTestContext(true, null);
        $customerGroup = $context->getCurrentCustomerGroup();
        $fallback = $context->getFallbackCustomerGroup();

        $result = $this->search(
            [
                'first' => [$customerGroup->getKey() => 20, $fallback->getKey() => 1],
                'second' => [$customerGroup->getKey() => 10, $fallback->getKey() => 1],
                'third' => [$customerGroup->getKey() => 12, $fallback->getKey() => 1],
                'fourth' => [$customerGroup->getKey() => 14, $fallback->getKey() => 1],
            ],
            ['second', 'third', 'fourth', 'first'],
            null,
            [],
            [new PriceFacet()],
            [],
            $context
        );

        /** @var $facet RangeFacetResult */
        $facet = $result->getFacets()[0];
        $this->assertInstanceOf('Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult', $facet);

        $this->assertEquals(110.00, $facet->getMin());
        $this->assertEquals(120.00, $facet->getMax());
    }

    public function testFacetWithFallbackCustomerGroupPrices()
    {
        $context = $this->getTestContext(true, null);
        $context->setFallbackCustomerGroup($this->getEkCustomerGroup());
        $fallback = $context->getFallbackCustomerGroup();

        $result = $this->search(
            [
                'first' => [$fallback->getKey() => 30],
                'second' => [$fallback->getKey() => 5],
                'third' => [$fallback->getKey() => 12],
                'fourth' => [$fallback->getKey() => 14],
            ],
            ['second', 'third', 'fourth', 'first'],
            null,
            [],
            [new PriceFacet()],
            [],
            $context
        );

        /** @var $facet RangeFacetResult */
        $facet = $result->getFacets()[0];

        $this->assertEquals(105.00, $facet->getMin());
        $this->assertEquals(130.00, $facet->getMax());
    }

    /**
     * @group skipElasticSearch
     */
    public function testFacetWithMixedCustomerGroupPrices()
    {
        $context = $this->getTestContext(true, null);
        $customerGroup = $context->getCurrentCustomerGroup();
        $fallback = $context->getFallbackCustomerGroup();

        $result = $this->search(
            [
                'first' => [$customerGroup->getKey() => 0, $fallback->getKey() => 5],
                'second' => [$fallback->getKey() => 50],
                'third' => [$customerGroup->getKey() => 12, $fallback->getKey() => 14],
                'fourth' => [$fallback->getKey() => 12],
            ],
            ['second', 'third', 'fourth', 'first'],
            null,
            [],
            [new PriceFacet()],
            [],
            $context
        );
        /** @var $facet RangeFacetResult */
        $facet = $result->getFacets()[0];

        $this->assertEquals(100.00, $facet->getMin());
        $this->assertEquals(150.00, $facet->getMax());
    }

    /**
     * @group skipElasticSearch
     */
    public function testFacetWithCurrencyFactor()
    {
        $context = $this->getTestContext(true, null);
        $customerGroup = $context->getCurrentCustomerGroup();
        $fallback = $context->getFallbackCustomerGroup();

        $context->getCurrency()->setFactor(2.5);

        $result = $this->search(
            [
                'first' => [$customerGroup->getKey() => 0, $fallback->getKey() => 5],
                'second' => [$fallback->getKey() => 50],
                'third' => [$customerGroup->getKey() => 12, $fallback->getKey() => 14],
                'fourth' => [$fallback->getKey() => 12],
            ],
            ['second', 'third', 'fourth', 'first'],
            null,
            [],
            [new PriceFacet()],
            [],
            $context
        );
        /** @var $facet RangeFacetResult */
        $facet = $result->getFacets()[0];

        $this->assertEquals(250.00, $facet->getMin());
        $this->assertEquals(375.00, $facet->getMax());
    }

    protected function getTestContext($displayGross, $discount = null)
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
     * @param \Shopware\Context\Struct\ShopContext $context
     * @param \Shopware\Models\Category\Category                    $category
     * @param array                                                 $prices
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
}
