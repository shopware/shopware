<?php

namespace Shopware\Core\System\CustomEntity;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class CustomEntityPersister
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function update(array $entities, ?string $appId): void
    {
        $names = array_column($entities, 'name');

        if (count($names) !== count(array_filter($names))) {
            throw new \RuntimeException('Some of the entities has no configured name');
        }

        $existing = $this->connection->fetchAllKeyValue(
            'SELECT name, created_at FROM custom_entity  WHERE name IN (:names)',
            ['names' => $names],
            ['names' => Connection::PARAM_STR_ARRAY]
        );

        $this->connection->executeStatement(
            'DELETE FROM custom_entity WHERE name IN (:names)',
            ['names' => $names],
            ['names' => Connection::PARAM_STR_ARRAY]
        );

        $inserts = new MultiInsertQueryQueue($this->connection);
        foreach ($entities as $entity) {
            $name = $entity['name'];

            $entity['fields'] = json_encode($entity['fields'], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
            $entity['app_id'] = $appId !== null ? Uuid::fromHexToBytes($appId) : null;

            $id = $entity['id'] ?? Uuid::randomHex();
            $entity['id'] = Uuid::fromHexToBytes($id);

            $entity['created_at'] = $existing[$name] ?? (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $entity['updated_at'] = isset($existing[$name]) ? (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null;

            $inserts->addInsert('custom_entity', $entity);
        }
        $inserts->execute();
    }
}
