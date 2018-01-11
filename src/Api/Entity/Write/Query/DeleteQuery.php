<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Write\Query;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Dbal\EntityDefinitionResolver;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Write\FieldAware\StorageAware;

class DeleteQuery extends WriteQuery
{
    /**
     * @var EntityDefinition|string
     */
    private $definition;

    /**
     * @var array
     */
    private $pkData;

    public function __construct($definition, array $pkData)
    {
        $this->definition = $definition;
        $this->pkData = $pkData;
    }

    public function isExecutable(): bool
    {
        return (bool) count($this->pkData);
    }

    public function execute(Connection $connection): int
    {
        $table = $this->definition::getEntityName();

        $pk = array_map(function ($value) {
            return Uuid::fromString($value)->getBytes();
        }, $this->pkData);

        return $connection->delete(EntityDefinitionResolver::escape($table), $pk);
    }

    public function getEntityDefinition(): string
    {
        return $this->definition;
    }

    public function getEntityPrimaryKey()
    {
        $pk = $this->definition::getPrimaryKeys();
        $data = [];

        if ($pk->count() === 1) {
            /** @var StorageAware|Field $field */
            $field = $pk->first();

            return $this->pkData[$field->getStorageName()];
        }

        /** @var StorageAware|Field $field */
        foreach ($pk as $field) {
            $data[$field->getPropertyName()] = $this->pkData[$field->getStorageName()];
        }

        return $data;
    }
}
