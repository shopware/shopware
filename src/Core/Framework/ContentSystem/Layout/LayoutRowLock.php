<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Every writer of a layout that reads before it writes, persisted mutations and the revision graph alike, runs
 * under this one lock, so a publish and a mutation of the same layout serialize instead of overwriting each other.
 * The lock is taken before anything else is read: under REPEATABLE READ the transaction's snapshot starts at its
 * first plain read, which then already sees the write of the transaction this one waited for.
 *
 * @internal
 *
 * @final
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\LayoutRowLockTest
 */
#[Package('framework')]
readonly class LayoutRowLock
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @template T
     *
     * @param \Closure(): T $operation
     *
     * @return T
     */
    public function run(string $layoutId, \Closure $operation): mixed
    {
        return $this->connection->transactional(function () use ($layoutId, $operation): mixed {
            $locked = $this->connection->fetchOne(
                <<<'SQL'
                    SELECT 1 FROM `content_layout` WHERE `id` = :id FOR UPDATE
                    SQL,
                ['id' => Uuid::fromHexToBytes($layoutId)],
            );

            if ($locked === false) {
                throw ContentSystemException::contentLayoutNotFound($layoutId);
            }

            return $operation();
        });
    }
}
