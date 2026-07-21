<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResultCollection;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
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

    public function testFiltersCanBeChainedWithoutChangingOriginalCollection(): void
    {
        $matchingUpdate = new EntityWriteResult('matching-id', ['active' => true], 'product', EntityWriteResult::OPERATION_UPDATE);
        $otherUpdate = new EntityWriteResult('other-id', ['stock' => 10], 'product', EntityWriteResult::OPERATION_UPDATE);
        $matchingInsert = new EntityWriteResult('insert-id', ['active' => true], 'product', EntityWriteResult::OPERATION_INSERT);
        $results = new EntityWriteResultCollection([$matchingUpdate, $otherUpdate, $matchingInsert]);

        $filtered = $results
            ->only(EntityWriteResult::OPERATION_UPDATE)
            ->withPayloadProperties('active');

        static::assertSame([$matchingUpdate], $filtered->getElements());
        static::assertSame([$matchingUpdate, $otherUpdate, $matchingInsert], $results->getElements());
    }

    public function testEmptyFiltersMatchNoResults(): void
    {
        $results = new EntityWriteResultCollection([
            new EntityWriteResult('product-id', ['active' => true], 'product', EntityWriteResult::OPERATION_UPDATE),
        ]);

        static::assertTrue($results->only()->isEmpty());
        static::assertTrue($results->withPayloadProperties()->isEmpty());
        static::assertSame([], $results->only('unknown')->getPrimaryKeys());
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

    public function testRejectsInvalidElements(): void
    {
        $results = new EntityWriteResultCollection();

        static::expectException(FrameworkException::class);

        /** @phpstan-ignore argument.type */
        $results->add(new \stdClass());
    }
}
