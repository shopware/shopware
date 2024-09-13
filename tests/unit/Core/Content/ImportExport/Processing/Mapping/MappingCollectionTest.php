<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Processing\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Processing\Mapping\Mapping;
use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(MappingCollection::class)]
class MappingCollectionTest extends TestCase
{
    public function testGet(): void
    {
        $mappingFoo = new Mapping('foo', 'bar');
        $mappingAsdf = new Mapping('asdf', 'zxcv');
        $mappingCollection = new MappingCollection([$mappingFoo, $mappingAsdf]);

        static::assertNotNull($mappingCollection->get('foo'));
        static::assertNotNull($mappingCollection->get('asdf'));

        static::assertNull($mappingCollection->get('bar'));
        static::assertNull($mappingCollection->get('zxcv'));

        static::assertSame($mappingFoo, $mappingCollection->get('foo'));
        static::assertSame($mappingAsdf, $mappingCollection->get('asdf'));
    }

    public function testGetMapped(): void
    {
        $mappingFoo = new Mapping('foo', 'bar');
        $mappingAsdf = new Mapping('asdf', 'zxcv');
        $mappingCollection = new MappingCollection([$mappingFoo, $mappingAsdf]);

        static::assertNull($mappingCollection->getMapped('foo'));
        static::assertNull($mappingCollection->getMapped('asdf'));

        static::assertNotNull($mappingCollection->getMapped('bar'));
        static::assertNotNull($mappingCollection->getMapped('zxcv'));

        static::assertSame($mappingFoo, $mappingCollection->getMapped('bar'));
        static::assertSame($mappingAsdf, $mappingCollection->getMapped('zxcv'));
    }

    public function testInvalidElement(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore-next-line intentionally wrong parameter provided */
        new MappingCollection([new ArrayEntity()]);
    }

    public function testFromIterableMappingCollection(): void
    {
        $mappingFoo = new Mapping('foo', 'bar');
        $mappingAsdf = new Mapping('asdf', 'zxcv');
        $mappingCollection = new MappingCollection([$mappingFoo, $mappingAsdf]);

        static::assertSame($mappingCollection, MappingCollection::fromIterable($mappingCollection));
    }

    public function testFromIterableArrayOfMapping(): void
    {
        $mappingFoo = new Mapping('foo', 'bar');
        $mappingAsdf = new Mapping('asdf', 'zxcv');
        $mappingCollection = MappingCollection::fromIterable([$mappingFoo, $mappingAsdf]);

        static::assertCount(2, $mappingCollection);
    }

    public function testFromIterableArrayOfAssocArray(): void
    {
        $mappingFoo = ['key' => 'foo', 'mappedKey' => 'bar'];
        $mappingAsdf = ['key' => 'asdf', 'mappedKey' => 'zxcv'];
        $idMapping = 'id';
        $mappingCollection = MappingCollection::fromIterable([$mappingFoo, $mappingAsdf, $idMapping]);

        static::assertCount(3, $mappingCollection);

        $mappingFoo = $mappingCollection->get('foo');
        static::assertInstanceOf(Mapping::class, $mappingFoo);
        static::assertSame('foo', $mappingFoo->getKey());

        $mappingAsdf = $mappingCollection->get('asdf');
        static::assertInstanceOf(Mapping::class, $mappingAsdf);
        static::assertSame('asdf', $mappingAsdf->getKey());

        $idMapping = $mappingCollection->get('id');
        static::assertInstanceOf(Mapping::class, $idMapping);
        static::assertSame('id', $idMapping->getKey());
        static::assertNull($idMapping->getDefaultValue());

        $idMappedKey = $mappingCollection->getMapped('id');
        static::assertInstanceOf(Mapping::class, $idMappedKey);
        static::assertSame('id', $idMappedKey->getMappedKey());
    }

    public function testInvalidMappingThrows(): void
    {
        $mappingFoo = ['mappedKey' => 'bar'];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('key is required in mapping');

        /** @phpstan-ignore-next-line intentionally wrong array provided */
        MappingCollection::fromIterable([$mappingFoo]);
    }

    public function testNotMappedNotOverridden(): void
    {
        $mapping1 = new Mapping('foo', 'bar');
        $mapping1Visited = false;
        $mapping2 = new Mapping('', 'zxcv');
        $mapping2Visited = false;
        $mapping3 = new Mapping('', 'asdf');
        $mapping3Visited = false;
        $mapping4 = new Mapping('', 'uiop');
        $mapping4Visited = false;
        $mappingCollection = new MappingCollection([$mapping1, $mapping2, $mapping3, $mapping4]);

        // key lookup should still work if the key was not empty
        $firstByKey = $mappingCollection->get('foo');
        static::assertNotNull($firstByKey);
        static::assertSame('bar', $firstByKey->getMappedKey());

        // it should not be possible to get the 'one' mapping that has the key of an empty string ''
        static::assertNull($mappingCollection->get(''));

        // but every Mapping should be in the collection regardless of empty keys
        static::assertCount(4, $mappingCollection);

        foreach ($mappingCollection as $mapped) {
            if ($mapped->getMappedKey() === 'bar') {
                $mapping1Visited = true;
            } elseif ($mapped->getMappedKey() === 'zxcv') {
                $mapping2Visited = true;
            } elseif ($mapped->getMappedKey() === 'asdf') {
                $mapping3Visited = true;
            } elseif ($mapped->getMappedKey() === 'uiop') {
                $mapping4Visited = true;
            }
        }

        static::assertTrue($mapping1Visited);
        static::assertTrue($mapping2Visited);
        static::assertTrue($mapping3Visited);
        static::assertTrue($mapping4Visited);
    }
}
