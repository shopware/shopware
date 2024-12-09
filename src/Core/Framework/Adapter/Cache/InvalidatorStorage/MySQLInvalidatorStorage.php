<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Shopware\Core\Framework\Log\Package;

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

        $this->connection->transactional(function (Connection $conn) use ($tags): void {
            $placeholders = implode(',', array_fill(0, \count($tags), '(?)'));
            $sql = 'INSERT IGNORE INTO ' . self::TABLE_NAME . ' (tag) VALUES ' . $placeholders;

            $conn->executeStatement(
                $sql,
                $tags,
                ['tag' => Types::STRING]
            );
        });
    }

    public function loadAndDelete(): array
    {
        return $this->connection->transactional(function (Connection $conn) {
            $tags = $conn->fetchFirstColumn(
                'SELECT tag FROM ' . self::TABLE_NAME . ' FOR UPDATE'
            );

            if (!empty($tags)) {
                $conn->executeStatement('DELETE FROM ' . self::TABLE_NAME);
            }

            return $tags;
        });
    }
}
