<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\Subscriber\ProductCategoryPathsSubscriber;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ProductCategoryPathsSubscriber::class)]
class ProductCategoryPathsSubscriberTest extends TestCase
{
    /**
     * @param array<string, mixed> $record
     * @param list<array{id: string}> $expectedCategories
     */
    #[TestDox('keeps existing categories and appends the resolved category: $_dataName')]
    #[DataProvider('existingCategoriesProvider')]
    public function testResolvedCategoriesAreMergedIntoExistingOnes(array $record, array $expectedCategories): void
    {
        $result = $this->importRecord($record, ['category_paths' => 'Existing']);

        static::assertSame($expectedCategories, $result['categories']);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $expectedRecord
     */
    #[TestDox('turns a category_paths cell into a category assignment only when it holds a value: $_dataName')]
    #[DataProvider('categoryPathsCellProvider')]
    public function testCategoryPathsCell(array $row, array $expectedRecord): void
    {
        static::assertSame($expectedRecord, $this->importRecord([], $row));
    }

    /**
     * @return iterable<string, array{record: array<string, mixed>, expectedCategories: list<array{id: string}>}>
     */
    public static function existingCategoriesProvider(): iterable
    {
        yield 'categories absent' => [
            'record' => [],
            'expectedCategories' => [['id' => 'resolved-id']],
        ];

        yield 'categories null' => [
            'record' => ['categories' => null],
            'expectedCategories' => [['id' => 'resolved-id']],
        ];

        yield 'categories empty string' => [
            'record' => ['categories' => ''],
            'expectedCategories' => [['id' => 'resolved-id']],
        ];

        yield 'categories string zero' => [
            'record' => ['categories' => '0'],
            'expectedCategories' => [['id' => 'resolved-id']],
        ];

        yield 'categories non-empty string' => [
            'record' => ['categories' => 'abc'],
            'expectedCategories' => [['id' => 'resolved-id']],
        ];

        yield 'categories empty array' => [
            'record' => ['categories' => []],
            'expectedCategories' => [['id' => 'resolved-id']],
        ];

        yield 'categories already assigned' => [
            'record' => ['categories' => [['id' => 'assigned-id']]],
            'expectedCategories' => [['id' => 'assigned-id'], ['id' => 'resolved-id']],
        ];
    }

    /**
     * @return iterable<string, array{row: array<string, mixed>, expectedRecord: array<string, mixed>}>
     */
    public static function categoryPathsCellProvider(): iterable
    {
        yield 'path is assigned' => [
            'row' => ['category_paths' => 'Existing'],
            'expectedRecord' => ['categories' => [['id' => 'resolved-id']]],
        ];

        yield 'zero string path is assigned' => [
            'row' => ['category_paths' => '0'],
            'expectedRecord' => ['categories' => [['id' => 'resolved-id']]],
        ];

        yield 'absent cell leaves the record' => [
            'row' => [],
            'expectedRecord' => [],
        ];

        yield 'null cell leaves the record' => [
            'row' => ['category_paths' => null],
            'expectedRecord' => [],
        ];

        yield 'empty string cell leaves the record' => [
            'row' => ['category_paths' => ''],
            'expectedRecord' => [],
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function importRecord(array $record, array $row): array
    {
        $categoryRepository = new StaticEntityRepository([['resolved-id']]);

        $subscriber = new ProductCategoryPathsSubscriber($categoryRepository, static::createStub(SyncServiceInterface::class));

        $event = new ImportExportBeforeImportRecordEvent(
            $record,
            $row,
            new Config([], ['sourceEntity' => ProductDefinition::ENTITY_NAME], []),
            Context::createDefaultContext()
        );

        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $subscriber->categoryPathsToAssignment($event);
        } finally {
            restore_error_handler();
        }

        return $event->getRecord();
    }
}
