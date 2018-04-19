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

namespace Shopware\Tests\Functional\Bundle\SearchBundle\Condition;

use Shopware\Bundle\SearchBundle\Condition\PropertyCondition;
use Shopware\Context\Struct\ShopContext;
use Shopware\Models\Category\Category;
use Shopware\Tests\Functional\Bundle\StoreFrontBundle\TestCase;

/**
 * @group elasticSearch
 */
class PropertyConditionTest extends TestCase
{
    public function testSinglePropertyConditionWithOneValue()
    {
        $properties = $this->helper->getProperties(3, 4);
        $values = $properties['propertyValues'];

        /*
         * Group 0:   0, 1, 2, 3
         * Group 1:   4, 5, 6, 7
         * Group 2:   8, 9, 10, 11
         */

        $first = $this->createPropertyCombination($properties, [0, 4]);
        $second = $this->createPropertyCombination($properties, [1, 5]);
        $third = $this->createPropertyCombination($properties, [2, 6]);
        $fourth = $this->createPropertyCombination($properties, [3, 7]);

        $conditions = [];

        $conditions[] = new PropertyCondition([
            $values[0]['id'],
        ]);

        $this->search(
            [
                'first' => $first,
                'second' => $second,
                'third' => $third,
                'fourth' => $fourth,
            ],
            ['first'],
            null,
            $conditions
        );
    }

    public function testSinglePropertyConditionWithTwoValues()
    {
        $properties = $this->helper->getProperties(3, 4);
        $values = $properties['propertyValues'];

        /*
         * Group 0:   0, 1, 2, 3
         * Group 1:   4, 5, 6, 7
         * Group 2:   8, 9, 10, 11
         */

        $first = $this->createPropertyCombination($properties, [0, 4]);
        $second = $this->createPropertyCombination($properties, [1, 5]);
        $third = $this->createPropertyCombination($properties, [2, 6]);
        $fourth = $this->createPropertyCombination($properties, [3, 7]);

        $conditions = [];

        $conditions[] = new PropertyCondition([
            $values[0]['id'],
            $values[1]['id'],
        ]);

        $this->search(
            [
                'first' => $first,
                'second' => $second,
                'third' => $third,
                'fourth' => $fourth,
            ],
            ['first', 'second'],
            null,
            $conditions
        );
    }

    public function testSinglePropertyConditionWithThreeValues()
    {
        $properties = $this->helper->getProperties(3, 4);
        $values = $properties['propertyValues'];

        /*
         * Group 0:   0, 1, 2, 3
         * Group 1:   4, 5, 6, 7
         * Group 2:   8, 9, 10, 11
         */

        $first = $this->createPropertyCombination($properties, [0, 4]);
        $second = $this->createPropertyCombination($properties, [1, 5]);
        $third = $this->createPropertyCombination($properties, [2, 6]);
        $fourth = $this->createPropertyCombination($properties, [3, 7]);

        $conditions = [];

        $conditions[] = new PropertyCondition([
            $values[0]['id'],
            $values[1]['id'],
            $values[3]['id'],
        ]);

        $this->search(
            [
                'first' => $first,
                'second' => $second,
                'third' => $third,
                'fourth' => $fourth,
            ],
            ['first', 'second', 'fourth'],
            null,
            $conditions
        );
    }

    public function testTwoPropertyConditionsWithOneValue()
    {
        $properties = $this->helper->getProperties(3, 4);
        $values = $properties['propertyValues'];

        /*
         * Group 0:   0, 1, 2, 3
         * Group 1:   4, 5, 6, 7
         * Group 2:   8, 9, 10, 11
         */

        $first = $this->createPropertyCombination($properties, [0, 4]);
        $second = $this->createPropertyCombination($properties, [0, 4]);
        $third = $this->createPropertyCombination($properties, [2, 6]);
        $fourth = $this->createPropertyCombination($properties, [3, 7]);

        $conditions = [];

        $conditions[] = new PropertyCondition([
            $values[0]['id'],
        ]);

        $conditions[] = new PropertyCondition([
            $values[4]['id'],
        ]);

        $this->search(
            [
                'first' => $first,
                'second' => $second,
                'third' => $third,
                'fourth' => $fourth,
            ],
            ['first', 'second'],
            null,
            $conditions
        );
    }

    public function testTwoPropertyConditionsWithTwoValues()
    {
        $properties = $this->helper->getProperties(3, 4);
        $values = $properties['propertyValues'];

        /*
         * Group 0:   0, 1, 2, 3
         * Group 1:   4, 5, 6, 7
         * Group 2:   8, 9, 10, 11
         */

        $first = $this->createPropertyCombination($properties, [0, 4]);
        $second = $this->createPropertyCombination($properties, [1, 5]);
        $third = $this->createPropertyCombination($properties, [1, 6]);
        $fourth = $this->createPropertyCombination($properties, [3, 5]);

        $conditions = [];

        $conditions[] = new PropertyCondition([
            $values[0]['id'],
            $values[1]['id'],
        ]);

        $conditions[] = new PropertyCondition([
            $values[4]['id'],
            $values[5]['id'],
        ]);

        $this->search(
            [
                'first' => $first,
                'second' => $second,
                'third' => $third,
                'fourth' => $fourth,
            ],
            ['first', 'second'],
            null,
            $conditions
        );
    }

    public function testTwoPropertyConditionsWithThreeValues()
    {
        $properties = $this->helper->getProperties(3, 4);
        $values = $properties['propertyValues'];

        /*
         * Group 0:   0, 1, 2, 3
         * Group 1:   4, 5, 6, 7
         * Group 2:   8, 9, 10, 11
         */

        $first = $this->createPropertyCombination($properties, [0, 4]);
        $second = $this->createPropertyCombination($properties, [1, 5]);
        $third = $this->createPropertyCombination($properties, [2, 6]);
        $fourth = $this->createPropertyCombination($properties, [3, 5]);

        $conditions = [];

        $conditions[] = new PropertyCondition([
            $values[0]['id'],
            $values[1]['id'],
            $values[2]['id'],
        ]);

        $conditions[] = new PropertyCondition([
            $values[4]['id'],
            $values[5]['id'],
            $values[6]['id'],
        ]);

        $this->search(
            [
                'first' => $first,
                'second' => $second,
                'third' => $third,
                'fourth' => $fourth,
            ],
            ['first', 'second', 'third'],
            null,
            $conditions
        );
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
