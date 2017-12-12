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

namespace Shopware\Api\Entity\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Write\EntityWriterInterface;
use Shopware\Api\Entity\Write\FieldAware\DefaultExtender;
use Shopware\Api\Entity\Write\FieldAware\FieldExtenderCollection;
use Shopware\Api\Entity\Write\FieldException\FieldExceptionStack;
use Shopware\Api\Entity\Write\Query\InsertQuery;
use Shopware\Api\Entity\Write\Query\UpdateQuery;
use Shopware\Api\Entity\Write\Query\WriteQuery;
use Shopware\Api\Entity\Write\Query\WriteQueryQueue;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Entity\Write\WriteResource;

class EntityWriter implements EntityWriterInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var DefaultExtender
     */
    private $defaultExtender;

    public function __construct(Connection $connection, DefaultExtender $defaultExtender)
    {
        $this->connection = $connection;
        $this->defaultExtender = $defaultExtender;
    }

    public function upsert(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $this->validateWriteInput($rawData);

        $queryQueue = $this->buildQueryQueue($definition, $rawData, $writeContext);

        $writeIdentifiers = $this->getWriteIdentifiers($queryQueue);

        $queryQueue->execute($this->connection);

        return $writeIdentifiers;
    }

    public function insert(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $this->validateWriteInput($rawData);

        $queryQueue = $this->buildQueryQueue($definition, $rawData, $writeContext);

        $writeIdentifiers = $this->getWriteIdentifiers($queryQueue);

        $queryQueue->ensureIs($definition, InsertQuery::class);
        $queryQueue->execute($this->connection);

        return $writeIdentifiers;
    }

    public function update(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $this->validateWriteInput($rawData);

        $queryQueue = $this->buildQueryQueue($definition, $rawData, $writeContext);

        $writeIdentifiers = $this->getWriteIdentifiers($queryQueue);

        $queryQueue->ensureIs($definition, UpdateQuery::class);
        $queryQueue->execute($this->connection);

        return $writeIdentifiers;
    }

    private function getWriteIdentifiers(WriteQueryQueue $queue): array
    {
        $identifiers = [];

        /*
         * @var string
         * @var UpdateQuery[]|InsertQuery[] $queries
         */
        foreach ($queue->getQueries() as $resource => $queries) {
            if (count($queries) === 0) {
                continue;
            }

            $identifiers[$resource] = [];

            /** @var WriteQuery[] $queries */
            foreach ($queries as $query) {
                $identifiers[$resource][] = $query->getEntityPrimaryKey();
            }
        }

        return $identifiers;
    }

    private function determineQueryTypes(WriteContext $writeContext)
    {
        $pkMapping = $writeContext->getPrimaryKeyMapping();

        foreach ($pkMapping as $table => $definition) {
            if (count($definition['columns']) === 1) {
                $writeContext->setExistingPrimaries(
                    $table,
                    $this->fetchSinglePrimaryKey($definition, $table)
                );
                continue;
            }

            $writeContext->setExistingPrimaries(
                $table,
                $this->fetchMultiColumnPrimaryKey($definition, $table)
            );
        }
    }

    private function buildQueryQueue(string $definition, array $rawData, WriteContext $writeContext): WriteQueryQueue
    {
        $exceptionStack = new FieldExceptionStack();
        $queryQueue = new WriteQueryQueue();

        $extender = new FieldExtenderCollection();
        $extender->addExtender($this->defaultExtender);

        /* @var EntityDefinition $definition */
        $queryQueue->setOrder($definition, ...$definition::getWriteOrder());

        foreach ($rawData as $row) {
            WriteResource::collectPrimaryKeys($row, $definition, $exceptionStack, $queryQueue, $writeContext, $extender);
        }

        $this->determineQueryTypes($writeContext);

        $queryQueue = new WriteQueryQueue();
        $exceptionStack = new FieldExceptionStack();

        foreach ($rawData as $row) {
            WriteResource::extract($row, $definition, $exceptionStack, $queryQueue, $writeContext, $extender);
        }

        $exceptionStack->tryToThrow();

        return $queryQueue;
    }

    private function validateWriteInput(array $data): void
    {
        $valid = array_keys($data) === range(0, count($data) - 1);

        if (!$valid) {
            throw new \InvalidArgumentException('Expected input to be array.');
        }
    }

    private function fetchSinglePrimaryKey(array $definition, string $table): array
    {
        $query = $this->connection->createQueryBuilder();

        $columns = array_keys($definition['columns']);
        $column = array_shift($columns);

        $ids = array_column($definition['rows'], $column);

        $query->addSelect(EntityDefinitionResolver::escape($column));
        $query->from(EntityDefinitionResolver::escape($table));

        $query->where($table . '.' . $column . ' IN (:ids)');
        $query->setParameter(':ids', $ids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchMultiColumnPrimaryKey(array $definition, string $table)
    {
        $query = $this->connection->createQueryBuilder();

        $columns = array_keys($definition['columns']);
        foreach ($columns as $column) {
            $query->addSelect(EntityDefinitionResolver::escape($column));
        }

        $query->from(EntityDefinitionResolver::escape($table));

        $counter = 0;
        foreach ($definition['rows'] as $key) {
            $where = [];
            foreach ($key as $column => $value) {
                $where[] = $column . ' = :key' . $counter;
                $query->setParameter('key' . $counter, $value);
                ++$counter;
            }
            $query->orWhere(implode(' AND ', $where));
        }

        return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
}
