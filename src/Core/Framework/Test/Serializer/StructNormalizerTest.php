<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Serializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @internal
 */
class StructNormalizerTest extends TestCase
{
    private StructNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new StructNormalizer();
    }

    public function testSupportFormat(): void
    {
        static::assertTrue($this->normalizer->supportsNormalization(new TestStruct()));
        static::assertFalse($this->normalizer->supportsNormalization('string'));
        static::assertFalse($this->normalizer->supportsNormalization(1));
        static::assertFalse($this->normalizer->supportsNormalization(null));
        static::assertFalse($this->normalizer->supportsNormalization(false));
        static::assertFalse($this->normalizer->supportsNormalization(['array']));
        static::assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalizationOfSimpleStruct(): void
    {
        $struct = new TestStruct();
        $struct->setFoo('bar');

        $expected = [
            'extensions' => [],
            'foo' => 'bar',
        ];

        static::assertEquals(
            $expected,
            $this->normalizer->normalize($struct)
        );
    }

    public function testNormalizationOfCollections(): void
    {
        $struct = new TestStruct();
        $struct->setFoo('bar');

        $collection = new TestStructCollection([$struct]);

        $expected = [
            ['extensions' => [], 'foo' => 'bar'],
        ];

        static::assertEquals(
            $expected,
            $this->normalizer->normalize($collection)
        );
    }

    public function testDenormalizationSupport(): void
    {
        static::assertTrue(
            $this->normalizer->supportsDenormalization([
                '_class' => TestStruct::class,
                'foo' => 'bar',
            ], 'array')
        );

        static::assertFalse($this->normalizer->supportsDenormalization('string', 'array'));
        static::assertFalse($this->normalizer->supportsDenormalization(1, 'array'));
        static::assertFalse($this->normalizer->supportsDenormalization(null, 'array'));
        static::assertFalse($this->normalizer->supportsDenormalization(false, 'array'));
        static::assertFalse($this->normalizer->supportsDenormalization(['array'], 'array'));
        static::assertFalse($this->normalizer->supportsDenormalization(new \stdClass(), 'array'));
    }

    public function testDenormalizeDate(): void
    {
        $date = date_create_from_format('Y-m-d H:i:s', date('Y-m-d H:i:s'));
        static::assertInstanceOf(\DateTime::class, $date);

        static::assertEquals(
            $date,
            $this->normalizer->denormalize($date->format(\DateTime::ATOM))
        );
    }

    /**
     * @return array<list<mixed>>
     */
    public static function denormalizeShouldReturnNonArraysProvider(): array
    {
        return [
            ['string'],
            [1],
            [null],
            [false],
            [new \stdClass()],
        ];
    }

    /**
     * @dataProvider denormalizeShouldReturnNonArraysProvider
     */
    public function testDenormalizeShouldReturnNonArrays(mixed $input): void
    {
        static::assertEquals($input, $this->normalizer->denormalize($input));
    }

    public function testDenormalizeShouldThrowIfNonStructGiven(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to unserialize a non-struct class: stdClass');

        $this->normalizer->denormalize(['_class' => 'stdClass']);
    }

    public function testDenormalizeWithConstructorParameters(): void
    {
        $structNormalized = [
            '_class' => ConstructorStruct::class,
            'name' => 'Peter',
        ];

        $struct = new ConstructorStruct('Peter');

        static::assertEquals($struct, $this->normalizer->denormalize($structNormalized));
    }

    public function testDenormalizeShouldThrowWithNonProvidedConstructorParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required constructor parameter missing: "$name".');

        $this->normalizer->denormalize(['_class' => ConstructorStruct::class]);
    }

    public function testDenormalizeShouldRecreateInstance(): void
    {
        $structNormalized = [
            '_class' => TestStruct::class,
            'foo' => 'bar',
        ];

        $struct = new TestStruct();
        $struct->setFoo('bar');

        static::assertEquals($struct, $this->normalizer->denormalize($structNormalized));
    }

    public function testDenormalizeWithSubobjects(): void
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

        static::assertEquals($struct, $this->normalizer->denormalize($structNormalized));
    }

    public function testDenormalizeWithNonExistingClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "ThisClass\DoesNot\Exists" does not exist');

        $this->normalizer->denormalize(['_class' => 'ThisClass\DoesNot\Exists']);
    }
}

/**
 * @internal
 */
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

/**
 * @internal
 *
 * @extends Collection<TestStruct>
 */
class TestStructCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return TestStruct::class;
    }
}

/**
 * @internal
 */
class AdvancedTestStruct extends TestStruct
{
    /**
     * @var list<TestStruct>
     */
    protected $subClasses = [];

    /**
     * @var array<string>
     */
    protected $meta = [];

    /**
     * @return list<TestStruct>
     */
    public function getSubClasses(): array
    {
        return $this->subClasses;
    }

    /**
     * @param list<TestStruct> $subClasses
     */
    public function setSubClasses(array $subClasses): void
    {
        $this->subClasses = $subClasses;
    }

    /**
     * @return array<string>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @param array<string> $meta
     */
    public function setMeta(array $meta): void
    {
        $this->meta = $meta;
    }
}

/**
 * @internal
 */
class ConstructorStruct extends Struct
{
    /**
     * @var string
     */
    protected $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
