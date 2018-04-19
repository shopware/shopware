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

namespace Shopware\Tests\Unit\StoreFrontBundle\Serializer;

use PHPUnit\Framework\TestCase;
use Shopware\Framework\Struct\Attribute;
use Shopware\Framework\Struct\Collection;
use Shopware\Framework\Struct\Struct;
use Shopware\Serializer\JsonSerializer;
use Shopware\Serializer\ObjectDeserializer;

class JsonSerializerTest extends TestCase
{
    /**
     * @var JsonSerializer
     */
    private $serializer;

    protected function setUp()
    {
        parent::setUp();
        $this->serializer = new JsonSerializer(new ObjectDeserializer());
    }

    /**
     * @dataProvider structCases
     */
    public function testStructDeserialize(Struct $struct)
    {
        static::assertEquals(
            $struct,
            $this->serializer->deserialize(
                $this->serializer->serialize($struct)
            )
        );
    }

    public function structCases()
    {
        return [
            [new EmptyStruct()],
            [new StructWithScalarConstructor(1, 1.11, 'string', ['foo' => 'bar'])],
            [new StructWithStructConstructor(new EmptyStruct())],
            [new StructWithStructConstructor(new StructWithStructConstructor(new EmptyStruct()))],
            [new StructWithStructArrayConstructor([
                new EmptyStruct(),
                new EmptyStruct(),
                new StructWithStructConstructor(new StructWithStructConstructor(new EmptyStruct())),
                new StructWithStructArrayConstructor([
                    new StructWithStructConstructor(new StructWithStructConstructor(new EmptyStruct())),
                ]),
            ])],
            [
                new StructWithStructConstructor(new Attribute([
                    'foo' => 'bar',
                    'bar' => 'foo',
                    'struct' => new EmptyStruct(),
                ])),
            ],
            [
                new StructCollection([
                    new IdStruct(1),
                    new IdStruct(2),
                    new IdStruct(3),
                    new IdStruct(4),
                ]),
            ],
        ];
    }
}

class StructCollection extends Collection
{
    /**
     * @var IdStruct[]
     */
    protected $elements = [];

    public function add(IdStruct $struct)
    {
        $this->elements[$struct->getId()] = $struct;
    }
}

class EmptyStruct extends Struct
{
}

class IdStruct extends Struct
{
    protected $id;

    /**
     * @param $id
     */
    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }
}

class StructWithStructArrayConstructor extends Struct
{
    /**
     * @var \Shopware\Framework\Struct\Struct[]
     */
    protected $structs;

    /**
     * @param \Shopware\Framework\Struct\Struct[] $structs
     */
    public function __construct(array $structs)
    {
        $this->structs = $structs;
        foreach ($structs as $struct) {
            if (!$struct instanceof Struct) {
                throw new \RuntimeException('Only struct classes accepted');
            }
        }
    }
}

class StructWithStructConstructor extends Struct
{
    /**
     * @var \Shopware\Framework\Struct\Struct|null
     */
    protected $struct;

    /**
     * @param \Shopware\Framework\Struct\Struct|null $struct
     */
    public function __construct(?Struct $struct)
    {
        $this->struct = $struct;
    }
}

class StructWithScalarConstructor extends Struct
{
    /**
     * @var int
     */
    protected $int;

    /**
     * @var float
     */
    protected $float;

    /**
     * @var string
     */
    protected $string;

    /**
     * @var array
     */
    protected $array;

    public function __construct(int $int, float $float, string $string, array $array)
    {
        $this->int = $int;
        $this->float = $float;
        $this->string = $string;
        $this->array = $array;
    }
}
