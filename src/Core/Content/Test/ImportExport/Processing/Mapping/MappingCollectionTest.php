<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Processing\Mapping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Processing\Mapping\Mapping;
use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Framework\Struct\ArrayEntity;

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

        static::assertSame('foo', $mappingCollection->get('foo')->getKey());
        static::assertSame('asdf', $mappingCollection->get('asdf')->getKey());

        static::assertSame('id', $mappingCollection->get('id')->getKey());
        static::assertSame('id', $mappingCollection->getMapped('id')->getMappedKey());

        static::assertNull($mappingCollection->get('id')->getDefault());
        static::assertNull($mappingCollection->get('id')->getMappedDefault());
    }

    public function testInvalidMappingThrows(): void
    {
        $mappingFoo = ['mappedKey' => 'bar'];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('key is required in mapping');

        MappingCollection::fromIterable([$mappingFoo]);
    }
}
