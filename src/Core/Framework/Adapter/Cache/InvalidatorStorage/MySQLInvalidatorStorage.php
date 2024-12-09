<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @codeCoverageIgnore @see \Shopware\Tests\Integration\Core\Framework\Adapter\Cache\InvalidatorStorage\MySQLInvalidatorStorageTest
 */
#[Package('core')]
class MySQLInvalidatorStorage extends AbstractInvalidatorStorage
{
    private const TABLE_NAME = 'invalidation_tags';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function store(array $tags): void
    {
        if (empty($tags)) {
            return;
        }

        $insertQueue = new MultiInsertQueryQueue($this->connection, chunkSize: 1000, ignoreErrors: true);
        $insertQueue->addInserts(
            self::TABLE_NAME,
            array_map(
                fn (string $tag) => ['id' => Uuid::randomBytes(), 'tag' => $tag],
                array_values($tags)
            )
        );

        $this->connection->transactional(fn () => $insertQueue->execute());
    }

    public function loadAndDelete(): array
    {
        return $this->connection->transactional(function (Connection $conn) {
            $rows = $conn->fetchAllAssociative(
                'SELECT id, tag FROM ' . self::TABLE_NAME . ' ORDER BY id FOR UPDATE'
            );

            if (empty($rows)) {
                return [];
            }

            $firstTagId = $rows[0]['id'];
            $lastTagId = $rows[array_key_last($rows)]['id'];

            $conn->executeStatement(
                'DELETE FROM ' . self::TABLE_NAME . ' WHERE id BETWEEN ? AND ?',
                [$firstTagId, $lastTagId]
            );

            return array_column($rows, 'tag');
        });
    }
}
