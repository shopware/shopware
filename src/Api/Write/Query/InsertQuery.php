<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Api\Write\Query;

use Doctrine\DBAL\Connection;
use Shopware\Api\Dbal\EntityDefinitionResolver;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Write\FieldAware\StorageAware;

class InsertQuery extends WriteQuery
{
    /**
     * @var array
     */
    private $payload;

    /**
     * @var string|EntityDefinition
     */
    private $definition;

    public function __construct(string $definition, array $payload)
    {
        $this->payload = $payload;
        $this->definition = $definition;
    }

    public function isExecutable(): bool
    {
        return (bool) count($this->payload);
    }

    public function execute(Connection $connection): int
    {
        $table = $this->definition::getEntityName();

        return $connection->insert(EntityDefinitionResolver::escape($table), $this->payload);
    }

    public function getPayload(): array
    {
        return $this->payload;
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

            return $this->payload[$field->getStorageName()];
        }

        /** @var StorageAware|Field $field */
        foreach ($pk as $field) {
            $data[$field->getPropertyName()] = $this->payload[$field->getStorageName()];
        }

        return $data;
    }
}
