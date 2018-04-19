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

use Shopware\Bundle\SearchBundle\Facet\ImmediateDeliveryFacet;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class ImmediateDeliveryFacetTest extends TestCase
{
    public function testFacetWithNoStock()
    {
        $result = $this->search(
            [
                'first' => ['inStock' => 10],
                'second' => ['inStock' => 0],
                'third' => ['inStock' => 10],
            ],
            ['first', 'second', 'third'],
            null,
            [],
            [new ImmediateDeliveryFacet()]
        );
        $facet = $result->getFacets()[0];
        $this->assertInstanceOf('Shopware\Bundle\SearchBundle\FacetResult\BooleanFacetResult', $facet);
    }

    public function testFacetWithMinPurchase()
    {
        $result = $this->search(
            [
                'first' => ['inStock' => 2, 'minPurchase' => 2],
                'second' => ['inStock' => 4, 'minPurchase' => 5],
                'third' => ['inStock' => 3, 'minPurchase' => 2],
            ],
            ['first', 'second', 'third'],
            null,
            [],
            [new ImmediateDeliveryFacet()]
        );
        $facet = $result->getFacets()[0];
        $this->assertInstanceOf('Shopware\Bundle\SearchBundle\FacetResult\BooleanFacetResult', $facet);
    }

    public function testFacetWithNoData()
    {
        $result = $this->search(
            [
                'first' => ['inStock' => 1, 'minPurchase' => 2],
                'second' => ['inStock' => 1, 'minPurchase' => 4],
                'third' => ['inStock' => 1, 'minPurchase' => 3],
            ],
            ['first', 'second', 'third'],
            null,
            [],
            [new ImmediateDeliveryFacet()]
        );
        $this->assertCount(0, $result->getFacets());
    }

    /**
     * @param $number
     * @param \Shopware\Models\Category\Category                    $category
     * @param \Shopware\Context\Struct\ShopContext $context
     * @param array                                                 $data
     *
     * @return array
     */
    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $data = ['inStock' => 0, 'minPurchase' => 1]
    ) {
        $product = parent::getProduct($number, $context, $category);

        $product['lastStock'] = true;
        $product['mainDetail'] = array_merge($product['mainDetail'], $data);

        return $product;
    }
}
