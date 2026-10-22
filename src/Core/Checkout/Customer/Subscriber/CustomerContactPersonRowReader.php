<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber\CustomerContactPersonRowReaderTest
 */
#[Package('checkout')]
class CustomerContactPersonRowReader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<string> $ids
     *
     * @return list<array<string, string|null>>
     */
    public function read(string $entity, array $ids, bool $hasAccountType, bool $versioned): array
    {
        $columns = ['`first_name`', '`last_name`', '`company`'];

        if ($hasAccountType) {
            $columns[] = '`account_type`';
        }

        if ($versioned) {
            $columns[] = 'LOWER(HEX(`version_id`)) AS `version_id`';
        }

        /** @var list<array<string, string|null>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            \sprintf('SELECT LOWER(HEX(`id`)) AS `id`, %s FROM `%s` WHERE `id` IN (:ids) FOR UPDATE', implode(', ', $columns), $entity),
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $rows;
    }
}
