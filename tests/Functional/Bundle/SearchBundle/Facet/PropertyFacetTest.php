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

use Shopware\Bundle\SearchBundle\Facet\PropertyFacet;
use Shopware\Bundle\SearchBundle\FacetResult\FacetResultGroup;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class PropertyFacetTest extends TestCase
{
    public function testPropertyFacet()
    {
        $properties = $this->helper->getProperties(2, 3);

        $firstCombination = $this->createPropertyCombination(
            $properties,
            [0, 1, 2]
        );

        $secondCombination = $this->createPropertyCombination(
            $properties,
            [1, 2, 3]
        );

        $thirdCombination = $this->createPropertyCombination(
            $properties,
            [2, 3, 4, 5]
        );

        $result = $this->search(
            [
                'first' => $firstCombination,
                'second' => $secondCombination,
                'third' => $thirdCombination,
                'fourth' => [],
            ],
            ['first', 'second', 'third', 'fourth'],
            null,
            [],
            [new PropertyFacet()]
        );

        $this->assertCount(1, $result->getFacets());

        /** @var $facet FacetResultGroup */
        $facet = $result->getFacets()[0];
        $this->assertInstanceOf('Shopware\Bundle\SearchBundle\FacetResult\FacetResultGroup', $facet);

        $this->assertCount(2, $facet->getFacetResults());
        foreach ($facet->getFacetResults() as $result) {
            /* @var $result ValueListFacetResult */
            $this->assertInstanceOf('Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult', $result);
            $this->assertCount(3, $result->getValues());
        }
    }

    public function testMultiplePropertySets()
    {
        $properties = $this->helper->getProperties(2, 3);
        $first = $this->createPropertyCombination($properties, [0, 1, 2]);
        $second = $this->createPropertyCombination($properties, [3, 4, 5]);

        $properties = $this->helper->getProperties(2, 3, 'PHP');
        $third = $this->createPropertyCombination($properties, [0, 1, 2]);
        $fourth = $this->createPropertyCombination($properties, [3, 4, 5]);

        $result = $this->search(
            [
                'first' => $first,
                'second' => $second,
                'third' => $third,
                'fourth' => $fourth,
            ],
            ['first', 'second', 'third', 'fourth'],
            null,
            [],
            [new PropertyFacet()]
        );

        $this->assertCount(1, $result->getFacets());

        /** @var $facet FacetResultGroup */
        foreach ($result->getFacets() as $facet) {
            $this->assertInstanceOf('Shopware\Bundle\SearchBundle\FacetResult\FacetResultGroup', $facet);

            $this->assertCount(4, $facet->getFacetResults());
            foreach ($facet->getFacetResults() as $result) {
                /* @var $result ValueListFacetResult */
                $this->assertInstanceOf('Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult', $result);
                $this->assertCount(3, $result->getValues());
            }
        }
    }

    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $properties = []
    ) {
        $product = parent::getProduct($number, $context, $category);
        $product = array_merge($product, $properties);

        return $product;
    }

    private function createPropertyCombination($properties, $indexes)
    {
        $combination = $properties;
        unset($combination['all']);

        $values = [];
        foreach ($properties['propertyValues'] as $index => $value) {
            if (in_array($index, $indexes)) {
                $values[] = $value;
            }
        }
        $combination['propertyValues'] = $values;

        return $combination;
    }
}
