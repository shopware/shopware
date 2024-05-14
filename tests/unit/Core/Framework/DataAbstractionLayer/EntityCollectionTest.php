<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\Struct\ArrayEntity;

/**
 * @internal
 */
#[CoversClass(EntityCollection::class)]
class EntityCollectionTest extends TestCase
{
    public function testSetCustomFields(): void
    {
        $collection = new EntityCollection([
            new MyCollectionEntity('element-1', ['foo' => 1, 'bar' => 1]),
            new MyCollectionEntity('element-2', ['foo' => 2, 'bar' => 2]),
        ]);

        $collection->setCustomFields([
            'element-1' => ['foo' => 3, 'bar' => 3, 'baz' => 3],
            'element-2' => ['foo' => 4, 'bar' => 4],
            'not-exists' => ['foo' => 5],
        ]);

        static::assertEquals([
            'element-1' => ['foo' => 3, 'bar' => 3, 'baz' => 3],
            'element-2' => ['foo' => 4, 'bar' => 4],
        ], $collection->getCustomFieldsValues());

        // no exception should occur
        (new EntityCollection())->setCustomFields([]);

        $collection = new EntityCollection([
            new ArrayEntity(['id' => 'element-1', 'foo' => 1, 'bar' => 1]),
        ]);

        static::expectException(\RuntimeException::class);
        $collection->setCustomFields([
            'element-1' => ['foo' => 3, 'bar' => 3, 'baz' => 3],
        ]);
    }

    public function testGetCustomFieldsValue(): void
    {
        // no exception should occur
        (new EntityCollection())->getCustomFieldsValue('foo');

        $collection = new EntityCollection([
            new MyCollectionEntity('element-1', ['foo' => 1, 'bar' => 1]),
            new MyCollectionEntity('element-2', ['foo' => 2]),
        ]);

        static::assertEquals(
            [
                'element-1' => 1,
                'element-2' => 2,
            ],
            $collection->getCustomFieldsValue('foo')
        );

        static::assertEquals(
            [
                'element-1' => 1,
                'element-2' => null,
            ],
            $collection->getCustomFieldsValue('bar')
        );

        $collection = new EntityCollection([
            new ArrayEntity(['id' => 'element-1', 'foo' => 1, 'bar' => 1]),
        ]);

        static::expectException(\RuntimeException::class);
        $collection->getCustomFieldsValue('foo');
    }

    public function testGetCustomFieldsValues(): void
    {
        // no exception should occur
        (new EntityCollection())->getCustomFieldsValues('foo');

        $collection = new EntityCollection([
            new MyCollectionEntity('element-1', ['foo' => 1, 'bar' => 1]),
            new MyCollectionEntity('element-2', ['foo' => 2, 'bar' => 2, 'baz' => 2]),
        ]);

        static::assertEquals([
            'element-1' => ['foo' => 1, 'bar' => 1],
            'element-2' => ['foo' => 2, 'bar' => 2, 'baz' => 2],
        ], $collection->getCustomFieldsValues());

        static::assertEquals([
            'element-1' => ['foo' => 1],
            'element-2' => ['foo' => 2],
        ], $collection->getCustomFieldsValues('foo'));

        static::assertEquals([
            'element-1' => ['foo' => 1, 'bar' => 1],
            'element-2' => ['foo' => 2, 'bar' => 2],
        ], $collection->getCustomFieldsValues('foo', 'bar'));

        static::assertEquals([
            'element-1' => ['foo' => 1],
            'element-2' => ['foo' => 2, 'baz' => 2],
        ], $collection->getCustomFieldsValues('foo', 'baz'));

        static::assertEquals([
            'element-1' => [],
            'element-2' => [],
        ], $collection->getCustomFieldsValues('not-exists'));

        static::assertEquals([
            'element-1' => [],
            'element-2' => [],
        ], $collection->getCustomFieldsValues('not-exists', 'both'));

        $collection = new EntityCollection([
            new ArrayEntity(['id' => 'element-1', 'foo' => 1, 'bar' => 1]),
        ]);

        static::expectException(\RuntimeException::class);
        $collection->getCustomFieldsValues('foo');
    }
}

/**
 * @internal
 */
class MyCollectionEntity extends Entity
{
    use EntityCustomFieldsTrait;

    /**
     * @param string $_uniqueIdentifier
     * @param array<string, mixed>|null $customFields
     */
    public function __construct(
        protected $_uniqueIdentifier,
        protected $customFields = []
    ) {
    }
}
