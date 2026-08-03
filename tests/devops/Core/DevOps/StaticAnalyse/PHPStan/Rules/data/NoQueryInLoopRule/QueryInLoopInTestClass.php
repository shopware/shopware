<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\MyFakeNamespace;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * The class name marks this as test code, where per-record fixture queries are acceptable.
 *
 * @internal
 */
class QueryInLoopInTestClass
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<string> $ids
     */
    public function fetchPerId(array $ids): void
    {
        foreach ($ids as $id) {
            $this->connection->fetchOne('SELECT id FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        }
    }
}
