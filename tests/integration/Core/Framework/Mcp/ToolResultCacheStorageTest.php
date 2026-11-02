<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Runs the batched age-based delete against the real database, including the integer LIMIT binding.
 *
 * @internal
 */
#[Package('framework')]
class ToolResultCacheStorageTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDeleteOlderThanKeepsOnlyRowsCreatedAfterTheThreshold(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        // Far in the past, so stored results that already exist in the database are not affected.
        $threshold = new \DateTimeImmutable('2000-01-01 12:00:00.000');

        $before = $this->insert($connection, $threshold->modify('-1 millisecond'));
        $at = $this->insert($connection, $threshold);
        $after = $this->insert($connection, $threshold->modify('+1 millisecond'));

        $deleted = static::getContainer()->get(ToolResultCacheStorage::class)->deleteOlderThan($threshold);

        static::assertSame(2, $deleted);
        static::assertSame([$after], $this->remaining($connection, [$before, $at, $after]));
    }

    private function insert(Connection $connection, \DateTimeImmutable $createdAt): string
    {
        $id = Uuid::randomBytes();
        $connection->insert('mcp_tool_result_cache', [
            'id' => $id,
            'session_id' => 'ttl-test',
            'mime_type' => 'application/json',
            'content' => '{}',
            'created_at' => $createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return Uuid::fromBytesToHex($id);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function remaining(Connection $connection, array $ids): array
    {
        /** @var list<string> $rows */
        $rows = $connection->fetchFirstColumn(
            'SELECT LOWER(HEX(`id`)) FROM `mcp_tool_result_cache` WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY],
        );

        return $rows;
    }
}
