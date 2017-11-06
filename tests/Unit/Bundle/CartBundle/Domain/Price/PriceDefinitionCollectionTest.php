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

namespace Shopware\Tests\Unit\Bundle\CartBundle\Domain\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Cart\Price\PriceDefinitionCollection;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;

class PriceDefinitionCollectionTest extends TestCase
{
    public function testCreateWithKeys(): void
    {
        $collection = new PriceDefinitionCollection([
            'a' => new \Shopware\Cart\Price\Struct\PriceDefinition(1, new TaxRuleCollection()),
            'b' => new \Shopware\Cart\Price\Struct\PriceDefinition(2, new TaxRuleCollection()),
        ]);

        $this->assertTrue($collection->has('a'));
        $this->assertTrue($collection->has('b'));
        $this->assertFalse($collection->has('c'));
    }

    public function testAddWithKey(): void
    {
        $collection = new PriceDefinitionCollection([
            'a' => new PriceDefinition(1, new TaxRuleCollection()),
            'b' => new PriceDefinition(2, new TaxRuleCollection()),
        ]);
        $collection->add(new PriceDefinition(3, new TaxRuleCollection()), 'c');

        $this->assertTrue($collection->has('a'));
        $this->assertTrue($collection->has('b'));
        $this->assertTrue($collection->has('c'));
    }

    public function testGetByKey(): void
    {
        $collection = new PriceDefinitionCollection([
            'a' => new \Shopware\Cart\Price\Struct\PriceDefinition(1, new TaxRuleCollection()),
            'b' => new \Shopware\Cart\Price\Struct\PriceDefinition(2, new TaxRuleCollection()),
        ]);

        $this->assertEquals(
            new \Shopware\Cart\Price\Struct\PriceDefinition(1, new TaxRuleCollection()),
            $collection->get('a')
        );
        $this->assertEquals(
            new \Shopware\Cart\Price\Struct\PriceDefinition(2, new TaxRuleCollection()),
            $collection->get('b')
        );
    }

    public function testRemoveWithKey(): void
    {
        $collection = new PriceDefinitionCollection([
            'a' => new \Shopware\Cart\Price\Struct\PriceDefinition(1, new TaxRuleCollection()),
            'b' => new PriceDefinition(2, new TaxRuleCollection()),
        ]);

        $this->assertTrue($collection->has('a'));
        $this->assertTrue($collection->has('b'));

        $collection->remove('a');
        $collection->remove('b');

        $this->assertFalse($collection->has('a'));
        $this->assertFalse($collection->has('b'));
    }
}
