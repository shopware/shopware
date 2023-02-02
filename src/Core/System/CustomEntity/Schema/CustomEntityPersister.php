<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Schema;

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

        $existings = $this->connection->fetchAllAssociativeIndexed(
            'SELECT `name`, LOWER(HEX(id)) as id, created_at FROM custom_entity WHERE `name` IN (:names)',
            ['names' => $names],
            ['names' => Connection::PARAM_STR_ARRAY]
        );

        if ($appId !== null) {
            $this->connection->executeStatement('DELETE FROM custom_entity WHERE app_id = :id', ['id' => Uuid::fromHexToBytes($appId)]);
        } else {
            $this->connection->executeStatement('DELETE FROM custom_entity WHERE app_id IS NULL');
        }

        $inserts = new MultiInsertQueryQueue($this->connection, 250, false, true);
        foreach ($entities as $entity) {
            $name = $entity['name'];

            $entity['fields'] = json_encode($entity['fields'], \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
            $entity['app_id'] = $appId !== null ? Uuid::fromHexToBytes($appId) : null;

            $id = isset($existings[$name]) ? $existings[$name]['id'] : Uuid::randomHex();
            $entity['id'] = Uuid::fromHexToBytes($id);

            $entity['created_at'] = isset($existings[$name]) ? $existings[$name]['created_at'] : (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $entity['updated_at'] = isset($existings[$name]) ? (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null;

            $inserts->addInsert('custom_entity', $entity);
        }
        $inserts->execute();
    }
}
