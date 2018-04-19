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

use Shopware\Bundle\SearchBundle\Facet\ManufacturerFacet;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Article\Supplier;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class ManufacturerFacetTest extends TestCase
{
    public function testWithNoManufacturer()
    {
        $result = $this->search(
            [
                'first' => null,
                'second' => null,
            ],
            ['first', 'second'],
            null,
            [],
            [new ManufacturerFacet()]
        );

        $this->assertCount(0, $result->getFacets());
    }

    public function testSingleManufacturer()
    {
        $supplier = $this->helper->createManufacturer();

        $result = $this->search(
            [
                'first' => $supplier,
                'second' => $supplier,
                'third' => null,
            ],
            ['first', 'second', 'third'],
            null,
            [],
            [new ManufacturerFacet()]
        );

        $facet = $result->getFacets()[0];

        /* @var $facet ValueListFacetResult */
        $this->assertInstanceOf('Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult', $facet);

        $this->assertCount(1, $facet->getValues());
        $this->assertEquals($supplier->getId(), $facet->getValues()[0]->getId());
    }

    public function testMultipleManufacturers()
    {
        $supplier1 = $this->helper->createManufacturer();
        $supplier2 = $this->helper->createManufacturer([
            'name' => 'Test-ProductManufacturer-2',
        ]);

        $result = $this->search(
            [
                'first' => $supplier1,
                'second' => $supplier1,
                'third' => $supplier2,
                'fourth' => null,
            ],
            ['first', 'second', 'third', 'fourth'],
            null,
            [],
            [new ManufacturerFacet()]
        );

        /** @var $facet ValueListFacetResult */
        $facet = $result->getFacets()[0];
        $this->assertCount(2, $facet->getValues());
    }

    /**
     * @param $number
     * @param \Shopware\Context\Struct\ShopContext $context
     * @param \Shopware\Models\Category\Category                    $category
     * @param Supplier                                              $manufacturer
     *
     * @return array
     */
    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $manufacturer = null
    ) {
        $product = parent::getProduct($number, $context, $category);

        if ($manufacturer) {
            $product['supplierId'] = $manufacturer->getId();
        } else {
            $product['supplierId'] = null;
        }

        return $product;
    }
}
