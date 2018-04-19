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

namespace Shopware\Tests\Unit;

use Enlight_Collection_ArrayCollection as ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class ArrayCollectionTest extends TestCase
{
    /**
     * Test case method
     */
    public function testArrayCollectionGet()
    {
        $collection = new ArrayCollection([
            'key_one' => 'wert1',
            'key_two' => 'wert2',
        ]);

        $this->assertEquals('wert1', $collection->key_one);
        $this->assertEquals('wert1', $collection->getKeyOne());
        $this->assertEquals('wert1', $collection->get('key_one'));
    }

    /**
     * Test case method
     */
    public function testArrayCollectionSet()
    {
        $collection = new ArrayCollection();

        $collection->setKeyOne('wert123');
        $this->assertEquals('wert123', $collection->getKeyOne());

        $collection->key_one = 'wert145';
        $this->assertEquals('wert145', $collection->key_one);
    }
}
