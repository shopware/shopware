<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;

class StructEncoderTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var StructEncoder
     */
    private $encoder;

    protected function setUp(): void
    {
        $this->encoder = $this->getContainer()->get(StructEncoder::class);
    }

    public function testApiAliasIsSet(): void
    {
        $foo = new MyTestStruct('foo', 'bar');

        $encoded = $this->encoder->encode($foo, 1, new ResponseFields([]));

        static::assertEquals(
            ['foo' => 'foo', 'bar' => 'bar', 'apiAlias' => 'test-struct'],
            $encoded
        );
    }

    public function testIncludesByApiAlias(): void
    {
        $foo = new MyTestStruct('foo', 'bar');

        $encoded = $this->encoder->encode($foo, 1, new ResponseFields([
            'test-struct' => ['foo'],
        ]));

        static::assertEquals(
            ['foo' => 'foo', 'apiAlias' => 'test-struct'],
            $encoded
        );
    }

    public function testIncludesSupportsExtensions(): void
    {
        $foo = new MyTestStruct('foo', 'bar');
        $foo->addExtension('myExtension', new MyTestStruct('foo2', 'bar2'));

        $fields = new ResponseFields([
            'test-struct' => ['foo', 'myExtension'],
        ]);

        $encoded = $this->encoder->encode($foo, 1, $fields);

        static::assertEquals(
            [
                'foo' => 'foo',
                'extensions' => [
                    'myExtension' => [
                        'foo' => 'foo2',
                        'apiAlias' => 'test-struct',
                    ],
                ],
                'apiAlias' => 'test-struct',
            ],
            $encoded
        );
    }

    public function testCollectionEncoding(): void
    {
        $collection = new StructCollection();
        $collection->add(new MyTestStruct(1, 1));
        $collection->add(new MyTestStruct(2, 2));
        $collection->add(new MyTestStruct(3, 3));

        $foo = new MyTestStruct('foo', $collection);

        $fields = new ResponseFields([
            'test-struct' => ['foo', 'bar'],
        ]);

        $encoded = $this->encoder->encode($foo, 1, $fields);

        static::assertEquals(
            [
                'foo' => 'foo',
                'bar' => [
                    ['foo' => 1, 'bar' => 1, 'apiAlias' => 'test-struct'],
                    ['foo' => 2, 'bar' => 2, 'apiAlias' => 'test-struct'],
                    ['foo' => 3, 'bar' => 3, 'apiAlias' => 'test-struct'],
                ],
                'apiAlias' => 'test-struct',
            ],
            $encoded
        );
    }

    public function testNestedCollections(): void
    {
        $collection = new StructCollection();

        $nested = new StructCollection();
        $nested->add(new MyTestStruct('nested1'));
        $nested->add(new MyTestStruct('nested2'));
        $nested->add(new AnotherStruct('nested3'));

        $collection->add(new AnotherStruct('another1', $nested));
        $collection->add(new AnotherStruct('another2'));
        $collection->add(new MyTestStruct('myTest1'));

        $root = new MyTestStruct('root', $collection);

        $fields = new ResponseFields([
            'test-struct' => ['foo', 'bar'],
            'another-struct' => ['bar'],
        ]);

        $encoded = $this->encoder->encode($root, 1, $fields);

        static::assertEquals(
            [
                'foo' => 'root',
                'bar' => [
                    [
                        'bar' => [
                            ['foo' => 'nested1', 'bar' => null, 'apiAlias' => 'test-struct'],
                            ['foo' => 'nested2', 'bar' => null, 'apiAlias' => 'test-struct'],
                            ['bar' => null, 'apiAlias' => 'another-struct'],
                        ],
                        'apiAlias' => 'another-struct',
                    ],
                    [
                        'bar' => null,
                        'apiAlias' => 'another-struct',
                    ],
                    [
                        'foo' => 'myTest1',
                        'bar' => null,
                        'apiAlias' => 'test-struct',
                    ],
                ],
                'apiAlias' => 'test-struct',
            ],
            $encoded
        );
    }
}

class MyTestStruct extends Struct
{
    public $foo;

    public $bar;

    public function __construct($foo = null, $bar = null)
    {
        $this->foo = $foo;
        $this->bar = $bar;
    }

    public function getApiAlias(): string
    {
        return 'test-struct';
    }
}

class AnotherStruct extends MyTestStruct
{
    public function getApiAlias(): string
    {
        return 'another-struct';
    }
}
