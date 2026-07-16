<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResultCollection;

/**
 * @internal
 */
#[CoversClass(EntityWriteResultCollection::class)]
class EntityWriteResultCollectionTest extends TestCase
{
    public function testFiltersByOperation(): void
    {
        $insert = new EntityWriteResult('insert-id', [], 'product', EntityWriteResult::OPERATION_INSERT);
        $update = new EntityWriteResult('update-id', [], 'product', EntityWriteResult::OPERATION_UPDATE);
        $delete = new EntityWriteResult('delete-id', [], 'product', EntityWriteResult::OPERATION_DELETE);

        $results = new EntityWriteResultCollection([$insert, $update, $delete]);

        static::assertSame([$insert, $update], $results->only(
            EntityWriteResult::OPERATION_INSERT,
            EntityWriteResult::OPERATION_UPDATE,
        )->getElements());
        static::assertCount(3, $results);
        static::assertSame('dal_entity_write_result_collection', $results->getApiAlias());
    }

    public function testFiltersWhenAnyPayloadPropertyIsPresent(): void
    {
        $withNull = new EntityWriteResult('null-id', ['active' => null], 'product', EntityWriteResult::OPERATION_UPDATE);
        $withName = new EntityWriteResult('name-id', ['name' => 'Example'], 'product', EntityWriteResult::OPERATION_UPDATE);
        $withoutMatch = new EntityWriteResult('stock-id', ['stock' => 10], 'product', EntityWriteResult::OPERATION_UPDATE);

        $results = new EntityWriteResultCollection([$withNull, $withName, $withoutMatch]);

        static::assertSame([$withNull, $withName], $results->withPayloadProperties('active', 'name')->getElements());
    }

    public function testReturnsPrimaryKeys(): void
    {
        $stringResults = new EntityWriteResultCollection([
            new EntityWriteResult('product-id', [], 'product', EntityWriteResult::OPERATION_UPDATE),
        ]);
        $compositeResults = new EntityWriteResultCollection([
            new EntityWriteResult(
                ['productId' => 'product-id', 'versionId' => 'version-id'],
                [],
                'product_translation',
                EntityWriteResult::OPERATION_UPDATE,
            ),
        ]);

        static::assertSame(['product-id'], $stringResults->getPrimaryKeys());
        static::assertSame([
            ['productId' => 'product-id', 'versionId' => 'version-id'],
        ], $compositeResults->getPrimaryKeys());
    }
}
