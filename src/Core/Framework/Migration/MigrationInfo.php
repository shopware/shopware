<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class MigrationInfo
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getFirstMigrationDate(): ?string
    {
        try {
            $firstMigrationDate = $this->connection->fetchOne(
                'SELECT MIN(`update`) FROM `migration` WHERE `update` IS NOT NULL'
            );
        } catch (\Throwable) {
            return null;
        }

        if (!\is_string($firstMigrationDate) || $firstMigrationDate === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($firstMigrationDate, new \DateTimeZone('UTC')))
                ->format(\DateTimeInterface::RFC3339_EXTENDED);
        } catch (\Throwable) {
            return null;
        }
    }
}
