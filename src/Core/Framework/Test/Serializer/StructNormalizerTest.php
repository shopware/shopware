<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Serializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

class StructNormalizerTest extends TestCase
{
    /**
     * @var StructNormalizer
     */
    private $normalizer;

    public function setUp()
    {
        $this->normalizer = new StructNormalizer();
    }

    public function testSupportFormat()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new TestStruct()));
        $this->assertFalse($this->normalizer->supportsNormalization('string'));
        $this->assertFalse($this->normalizer->supportsNormalization(1));
        $this->assertFalse($this->normalizer->supportsNormalization(null));
        $this->assertFalse($this->normalizer->supportsNormalization(false));
        $this->assertFalse($this->normalizer->supportsNormalization(['array']));
        $this->assertFalse($this->normalizer->supportsNormalization(new \StdClass()));
    }

    public function testNormalizationOfSimpleStruct()
    {
        $struct = new TestStruct();
        $struct->setFoo('bar');

        $expected = [
            '_class' => get_class($struct),
            'extensions' => [],
            'foo' => 'bar',
        ];

        $this->assertEquals(
            $expected,
            $this->normalizer->normalize($struct)
        );
    }

    public function testNormalizationOfCollections()
    {
        $struct = new TestStruct();
        $struct->setFoo('bar');

        $collection = new TestStructCollection([$struct]);

        $expected = [
            '_class' => get_class($collection),
            'elements' => [
                ['_class' => get_class($struct), 'extensions' => [], 'foo' => 'bar'],
            ],
            'extensions' => [],
            '_pointer' => 0,
        ];

        $this->assertEquals(
            $expected,
            $this->normalizer->normalize($collection)
        );
    }

    public function testDenormalizationSupport()
    {
        $this->assertTrue(
            $this->normalizer->supportsDenormalization([
                '_class' => TestStruct::class,
                'foo' => 'bar',
            ], 'array')
        );

        $this->assertFalse($this->normalizer->supportsDenormalization('string', 'array'));
        $this->assertFalse($this->normalizer->supportsDenormalization(1, 'array'));
        $this->assertFalse($this->normalizer->supportsDenormalization(null, 'array'));
        $this->assertFalse($this->normalizer->supportsDenormalization(false, 'array'));
        $this->assertFalse($this->normalizer->supportsDenormalization(['array'], 'array'));
        $this->assertFalse($this->normalizer->supportsDenormalization(new \StdClass(), 'array'));
    }

    public function testDenormalizeDate()
    {
        $date = date_create_from_format('Y-m-d H:i:s', date('Y-m-d H:i:s'));

        $this->assertEquals(
            $date,
            $this->normalizer->denormalize($date->format(\DateTime::ATOM))
        );
    }

    public function denormalizeShouldReturnNonArraysProvider()
    {
        return [
            ['string'],
            [1],
            [null],
            [false],
            [new \StdClass()],
        ];
    }

    /**
     * @param mixed $input
     * @dataProvider denormalizeShouldReturnNonArraysProvider
     */
    public function testDenormalizeShouldReturnNonArrays($input)
    {
        $this->assertEquals($input, $this->normalizer->denormalize($input));
    }

    public function testDenormalizeShouldThrowIfNonStructGiven()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to unserialize a non-struct class: stdClass');

        $this->normalizer->denormalize(['_class' => 'StdClass']);
    }

    public function testDenormalizeWithConstructorParameters()
    {
        $structNormalized = [
            '_class' => ConstructorStruct::class,
            'name' => 'Peter',
        ];

        $struct = new ConstructorStruct('Peter');

        $this->assertEquals($struct, $this->normalizer->denormalize($structNormalized));
    }

    public function testDenormalizeShouldThrowWithNonProvidedConstructorParameters()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required constructor Parameter Missing: "$name".');

        $this->normalizer->denormalize(['_class' => ConstructorStruct::class]);
    }

    public function testDenormalizeShouldRecreateInstance()
    {
        $structNormalized = [
            '_class' => TestStruct::class,
            'foo' => 'bar',
        ];

        $struct = new TestStruct();
        $struct->setFoo('bar');

        $this->assertEquals($struct, $this->normalizer->denormalize($structNormalized));
    }

    public function testDenormalizeWithSubobjects()
    {
        $structNormalized = [
            '_class' => AdvancedTestStruct::class,
            'foo' => 'bar',
            'subClasses' => [
                ['_class' => TestStruct::class, 'foo' => 'wusel'],
                ['_class' => TestStruct::class, 'foo' => 'gusel'],
            ],
            'meta' => [
                'keyA' => 'valueA',
                'keyB' => 'valueB',
            ],
        ];

        $subStruct = new TestStruct();
        $subStruct->setFoo('wusel');

        $subStruct2 = new TestStruct();
        $subStruct2->setFoo('gusel');

        $struct = new AdvancedTestStruct();
        $struct->setFoo('bar');
        $struct->setSubClasses([$subStruct, $subStruct2]);
        $struct->setMeta(['keyA' => 'valueA', 'keyB' => 'valueB']);

        $this->assertEquals($struct, $this->normalizer->denormalize($structNormalized));
    }

    public function testDenormalizeWithNonExistingClass()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class ThisClass\DoesNot\Exists does not exist');

        $this->normalizer->denormalize(['_class' => 'ThisClass\DoesNot\Exists']);
    }
}

class TestStruct extends Struct
{
    /**
     * @var string
     */
    protected $foo;

    public function getFoo(): string
    {
        return $this->foo;
    }

    public function setFoo(string $foo): void
    {
        $this->foo = $foo;
    }
}

class TestStructCollection extends Collection
{
    public function add(TestStruct $struct)
    {
        $this->elements[] = $struct;
    }
}

class AdvancedTestStruct extends TestStruct
{
    /**
     * @var TestStruct[]
     */
    protected $subClasses = [];

    /**
     * @var string[]
     */
    protected $meta = [];

    /**
     * @return TestStruct[]
     */
    public function getSubClasses(): array
    {
        return $this->subClasses;
    }

    public function setSubClasses(array $subClasses): void
    {
        $this->subClasses = $subClasses;
    }

    /**
     * @return string[]
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @param string[] $meta
     */
    public function setMeta(array $meta): void
    {
        $this->meta = $meta;
    }
}

class ConstructorStruct extends Struct
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $age;

    public function __construct(string $name, int $age = 18)
    {
        $this->name = $name;
    }
}
