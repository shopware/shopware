<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IndexerQueuer::class)]
class IndexerQueuerTest extends TestCase
{
    #[TestDox('getIndexers returns an empty list when nothing is queued')]
    public function testGetIndexersWithoutRow(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        static::assertSame([], (new IndexerQueuer($connection))->getIndexers());
    }

    #[TestDox('getIndexers upgrades the old int format to option arrays')]
    public function testGetIndexersUpgradesOldFormat(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 'config-id',
            'configuration_value' => (string) json_encode(['_value' => [
                'product.indexer' => ['skip-seo'],
                'category.indexer' => 1,
            ]]),
        ]);

        static::assertSame(
            [
                'product.indexer' => ['skip-seo'],
                'category.indexer' => [],
            ],
            (new IndexerQueuer($connection))->getIndexers()
        );
    }

    #[TestDox('finishIndexer removes only the given indexers and keeps the rest')]
    public function testFinishIndexerKeepsRemaining(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 'config-id',
            'configuration_value' => (string) json_encode(['_value' => [
                'product.indexer' => ['skip-seo'],
                'category.indexer' => 1,
            ]]),
        ]);

        $connection->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function (string $table, array $data, array $criteria): int {
                static::assertSame('system_config', $table);
                static::assertSame(['id' => 'config-id'], $criteria);
                static::assertSame(
                    ['_value' => ['category.indexer' => []]],
                    json_decode((string) $data['configuration_value'], true)
                );

                return 1;
            });

        (new IndexerQueuer($connection))->finishIndexer(['product.indexer']);
    }

    #[TestDox('finishIndexer deletes the config row when no indexer remains')]
    public function testFinishIndexerDeletesEmptyRow(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 'config-id',
            'configuration_value' => (string) json_encode(['_value' => ['product.indexer' => []]]),
        ]);

        $connection->expects($this->once())
            ->method('delete')
            ->willReturnCallback(static function (string $table, array $criteria): int {
                static::assertSame('system_config', $table);
                static::assertSame(['id' => 'config-id'], $criteria);

                return 1;
            });

        (new IndexerQueuer($connection))->finishIndexer(['product.indexer']);
    }

    #[TestDox('registerIndexer inserts a new config row when none exists')]
    public function testRegisterIndexerInsertsNewRow(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $connection->expects($this->once())
            ->method('insert')
            ->willReturnCallback(static function (string $table, array $data): int {
                static::assertSame('system_config', $table);
                static::assertSame(IndexerQueuer::INDEXER_KEY, $data['configuration_key']);
                static::assertSame(
                    ['_value' => ['product.indexer' => ['skip-seo']]],
                    json_decode((string) $data['configuration_value'], true)
                );

                return 1;
            });

        IndexerQueuer::registerIndexer($connection, 'product.indexer', ['skip-seo']);
    }

    #[TestDox('registerIndexer merges and dedupes required indexers into an existing row')]
    public function testRegisterIndexerMergesExistingOptions(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 'config-id',
            'configuration_value' => (string) json_encode(['_value' => [
                'product.indexer' => ['skip-seo'],
                'legacy.indexer' => 1,
            ]]),
        ]);

        $connection->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function (string $table, array $data, array $criteria): int {
                static::assertSame(['id' => 'config-id'], $criteria);
                // array_unique() preserves keys, so the merged options carry a key gap
                // and serialize as a JSON object instead of a list
                static::assertSame(
                    ['_value' => [
                        'product.indexer' => [0 => 'skip-seo', 2 => 'skip-cheapest-price'],
                        'legacy.indexer' => [],
                    ]],
                    json_decode((string) $data['configuration_value'], true)
                );

                return 1;
            });

        IndexerQueuer::registerIndexer($connection, 'product.indexer', ['skip-seo', 'skip-cheapest-price']);
    }

    #[TestDox('registerIndexer keeps a full run when some updaters are registered after it')]
    public function testRegisterIndexerKeepsAFullRunWhenSomeUpdatersFollow(): void
    {
        static::assertSame(
            ['_value' => ['product.indexer' => []]],
            $this->registerIndexerOnTopOf(['product.indexer' => []], ['product.cheapest-price'])
        );
    }

    #[TestDox('registerIndexer turns some registered updaters into a full run when a full run is registered')]
    public function testRegisterIndexerTurnsSomeUpdatersIntoAFullRun(): void
    {
        static::assertSame(
            ['_value' => ['product.indexer' => []]],
            $this->registerIndexerOnTopOf(['product.indexer' => ['product.states']], [])
        );
    }

    /**
     * Registers the product indexer on top of an already queued config and returns the config as it is written.
     *
     * @param array<string, list<string>> $queued
     * @param list<string> $requiredIndexers
     */
    private function registerIndexerOnTopOf(array $queued, array $requiredIndexers): mixed
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 'config-id',
            'configuration_value' => (string) json_encode(['_value' => $queued]),
        ]);

        $written = null;
        $connection->method('update')->willReturnCallback(static function (string $table, array $data) use (&$written): int {
            $written = json_decode((string) $data['configuration_value'], true);

            return 1;
        });

        IndexerQueuer::registerIndexer($connection, 'product.indexer', $requiredIndexers);

        return $written;
    }
}
