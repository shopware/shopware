<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1773397853BackfillDigitalProductStates;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(Migration1773397853BackfillDigitalProductStates::class)]
class Migration1773397853BackfillDigitalProductStatesTest extends TestCase
{
    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->ids = new IdsCollection();
        $this->connection->delete('system_config', ['configuration_key' => IndexerQueuer::INDEXER_KEY]);
    }

    protected function tearDown(): void
    {
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $this->connection->delete('product', ['id' => $this->ids->getBytes('digital-null'), 'version_id' => $versionId]);
        $this->connection->delete('product', ['id' => $this->ids->getBytes('digital-non-null'), 'version_id' => $versionId]);
        $this->connection->delete('product', ['id' => $this->ids->getBytes('physical-null'), 'version_id' => $versionId]);
        $this->connection->delete('system_config', ['configuration_key' => IndexerQueuer::INDEXER_KEY]);

        parent::tearDown();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1773397853,
            (new Migration1773397853BackfillDigitalProductStates())->getCreationTimestamp()
        );
    }

    public function testMigrationQueuesProductIndexerWithoutImmediateBackfill(): void
    {
        $this->createProduct($this->ids->getBytes('digital-null'), 'digital', null);
        $this->createProduct($this->ids->getBytes('digital-non-null'), 'digital', ['is-physical']);
        $this->createProduct($this->ids->getBytes('physical-null'), 'physical', null);

        $migration = new Migration1773397853BackfillDigitalProductStates();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $firstStates = $this->fetchStates($this->ids->get('digital-null'));
        $secondStates = $this->fetchStates($this->ids->get('digital-non-null'));
        $thirdStates = $this->fetchStates($this->ids->get('physical-null'));

        static::assertNull($firstStates);
        static::assertSame(['is-physical'], json_decode((string) $secondStates, true, 512, \JSON_THROW_ON_ERROR));
        static::assertNull($thirdStates);

        static::assertSame(
            ['product.indexer' => []],
            (new IndexerQueuer($this->connection))->getIndexers()
        );
    }

    /**
     * @param list<string>|null $states
     */
    private function createProduct(string $id, string $type, ?array $states): void
    {
        $this->connection->insert('product', [
            'id' => $id,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'stock' => 10,
            'available_stock' => 10,
            'is_closeout' => 0,
            'type' => $type,
            'states' => $states === null ? null : json_encode($states, \JSON_THROW_ON_ERROR),
        ]);
    }

    private function fetchStates(string $id): ?string
    {
        $states = $this->connection->fetchOne(
            <<<'SQL'
            SELECT `states`
            FROM `product`
            WHERE `id` = UNHEX(:id)
              AND `version_id` = :versionId
            SQL,
            [
                'id' => $id,
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        return \is_string($states) ? $states : null;
    }
}
