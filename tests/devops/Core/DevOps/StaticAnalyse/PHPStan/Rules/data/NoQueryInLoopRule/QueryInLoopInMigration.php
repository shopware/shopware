<?php declare(strict_types=1);

namespace Shopware\Core\Migration\MyFakeNamespace;

use Doctrine\DBAL\Connection;

/**
 * A migration runs once, and one that already ran on a shop cannot be changed, so its loops are not reported.
 *
 * @internal
 */
class QueryInLoopInMigration
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<string> $names
     */
    public function update(array $names): void
    {
        foreach ($names as $name) {
            $this->connection->fetchOne('SELECT id FROM product WHERE name = :name', ['name' => $name]);
        }
    }
}
