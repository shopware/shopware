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

namespace Shopware\Tests\Unit\StoreFrontBundle\Common;

use PHPUnit\Framework\TestCase;
use Shopware\Framework\Struct\Collection;
use Shopware\Framework\Struct\Struct as BaseStruct;
use Shopware\Framework\Struct\Struct;

class SimpleStruct extends BaseStruct
{
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }
}

class CollectionStruct extends Collection
{
    /**
     * @var SimpleStruct[]
     */
    protected $elements = [];

    public function add(SimpleStruct $struct)
    {
        $this->elements[$struct->getValue()] = $struct;
    }
}

class CloneStructTest extends TestCase
{
    public function testNestedStructCloning()
    {
        $simple = new SimpleStruct(
            new SimpleStruct('initial')
        );

        $clone = clone $simple;
        $simple->setValue('modified');

        $this->assertInstanceOf(SimpleStruct::class, $clone->getValue());
        $this->assertEquals('initial', $clone->getValue()->getValue());
    }

    public function testCollectionCloning()
    {
        $collection = new CollectionStruct([
            new SimpleStruct(1),
            new SimpleStruct('A'),
            new SimpleStruct(2),
        ]);

        $clone = clone $collection;

        $this->assertEquals($clone, $collection);
    }

    public function testNestedArrayCloning()
    {
        $simple = new SimpleStruct(
            [
                new SimpleStruct('struct 1'),
                new SimpleStruct('struct 2'),
            ]
        );

        $clone = clone $simple;

        /** @var $nested SimpleStruct[] */
        $nested = $simple->getValue();
        $nested[0]->setValue('struct 3');

        $nested = $clone->getValue();
        $this->assertEquals('struct 1', $nested[0]->getValue());
        $this->assertEquals('struct 2', $nested[1]->getValue());

        $simple->setValue('override');
        $this->assertEquals('struct 1', $nested[0]->getValue());
        $this->assertEquals('struct 2', $nested[1]->getValue());
    }

    public function testAssociatedArrayCloning()
    {
        $simple = new SimpleStruct(
            [
                'struct1' => new SimpleStruct('struct 1'),
                'struct2' => new SimpleStruct('struct 2'),
            ]
        );

        $clone = clone $simple;
        $simple->setValue(null);

        /** @var $nested SimpleStruct[] */
        $nested = $clone->getValue();
        $this->assertArrayHasKey('struct1', $nested);
        $this->assertArrayHasKey('struct2', $nested);

        $clone->setValue('test123');
        $this->assertNull($simple->getValue());
    }

    public function testRecursiveArrayCloning()
    {
        $simple = new SimpleStruct(
            [
                [new SimpleStruct('struct 1'), new SimpleStruct('struct 1')],
                [new SimpleStruct('struct 2'), new SimpleStruct('struct 2')],
            ]
        );

        $clone = clone $simple;
        $simple->setValue(null);

        /** @var $value SimpleStruct[][] */
        $value = $clone->getValue();
        $this->assertCount(2, $value[0]);
        $this->assertCount(2, $value[1]);

        $this->assertEquals('struct 1', $value[0][0]->getValue());
    }
}
