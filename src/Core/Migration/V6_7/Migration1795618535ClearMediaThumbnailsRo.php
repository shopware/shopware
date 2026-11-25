<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1795618535ClearMediaThumbnailsRo extends MigrationStep
{
    private const BATCH_SIZE = 1000;

    public function getCreationTimestamp(): int
    {
        return 1795618535;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'media', 'thumbnails_ro')) {
            return;
        }

        $query = $connection->createQueryBuilder()
            ->select('id')
            ->from('media')
            ->where('thumbnails_ro IS NOT NULL')
            ->orderBy('id')
            ->setMaxResults(self::BATCH_SIZE);

        $ids = $query->fetchFirstColumn();
        $query->andWhere('id > :cursor');

        while ($ids !== []) {
            $connection->executeStatement(
                'UPDATE media SET thumbnails_ro = NULL WHERE id IN (:ids) AND thumbnails_ro IS NOT NULL',
                ['ids' => $ids],
                ['ids' => ArrayParameterType::BINARY]
            );

            if (\count($ids) < self::BATCH_SIZE) {
                return;
            }

            $query->setParameter('cursor', $ids[\array_key_last($ids)], ParameterType::BINARY);
            $ids = $query->fetchFirstColumn();
        }
    }
}
