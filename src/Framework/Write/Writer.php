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

namespace Shopware\Framework\Write;

use Doctrine\DBAL\Connection;
use Shopware\Framework\Write\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Write\FieldException\FieldExceptionStack;
use Shopware\Framework\Write\Query\InsertQuery;
use Shopware\Framework\Write\Query\UpdateQuery;
use Shopware\Framework\Write\Query\WriteQueryQueue;

class Writer
{
    /**
     * @var ResourceRegistry
     */
    private $resourceRegistry;

    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var SqlGateway
     */
    private $sqlGateway;

    public function __construct(
        SqlGateway $sqlGateway,
        ResourceRegistry $resourceRegistry,
        Connection $connection
    ) {
        $this->resourceRegistry = $resourceRegistry;
        $this->connection = $connection;
        $this->sqlGateway = $sqlGateway;
    }

    public function upsert(string $resourceClass, array $rawData, WriteContext $writeContext, FieldExtenderCollection $extender): array
    {
        $resource = $this->resourceRegistry->get($resourceClass);

        $exceptionStack = new FieldExceptionStack();
        $queryQueue = new WriteQueryQueue();

        $queryQueue->setOrder(
            $resourceClass,
            ...$resource->getWriteOrder()
        );

        $resource->extract($rawData, $exceptionStack, $queryQueue, $this->sqlGateway, $writeContext, $extender);

        $exceptionStack->tryToThrow();
        $writeIdentifiers = $this->getWriteIdentifiers($queryQueue);

        $queryQueue->execute($this->connection);

        return $writeIdentifiers;
    }

    public function insert(string $resourceClass, array $rawData, WriteContext $writeContext, FieldExtenderCollection $extender)
    {
        $resource = $this->resourceRegistry->get($resourceClass);

        $exceptionStack = new FieldExceptionStack();
        $queryQueue = new WriteQueryQueue();

        $queryQueue->setOrder(
            $resourceClass,
            ...$resource->getWriteOrder()
        );

        $resource->extract($rawData, $exceptionStack, $queryQueue, $this->sqlGateway, $writeContext, $extender);

        $exceptionStack->tryToThrow();
        $writeIdentifiers = $this->getWriteIdentifiers($queryQueue);

        $queryQueue->execute($this->connection);

        return $writeIdentifiers;
    }

    public function update(string $resourceClass, array $rawData, WriteContext $writeContext, FieldExtenderCollection $extender)
    {
        $resource = $this->resourceRegistry->get($resourceClass);

        $exceptionStack = new FieldExceptionStack();
        $queryQueue = new WriteQueryQueue();

        $queryQueue->setOrder(
            $resourceClass,
            ...$resource->getWriteOrder()
        );

        $resource->extract($rawData, $exceptionStack, $queryQueue, $this->sqlGateway, $writeContext, $extender);

        $exceptionStack->tryToThrow();
        $writeIdentifiers = $this->getWriteIdentifiers($queryQueue);

        $queryQueue->execute($this->connection);

        return $writeIdentifiers;
    }

    private function getWriteIdentifiers(WriteQueryQueue $queue): array
    {
        $changedIdentifiers = [];

        /*
         * @var string
         * @var UpdateQuery[]|InsertQuery[] $query
         */
        foreach ($queue->getQueries() as $resource => $queries) {
            $changedIdentifiers[$resource] = [];

            if (empty($queries)) {
                continue;
            }

            foreach ($queries as $query) {
                $data = [];

                if ($query instanceof InsertQuery) {
                    $data = $query->getPayload();
                } elseif ($query instanceof UpdateQuery) {
                    $data = $query->getPrimaryKeyData();
                }

                foreach ($data as $key => $value) {
                    $changedIdentifiers[$resource][$key][] = $value;
                }
            }

            foreach ($changedIdentifiers[$resource] as $field => $values) {
                $changedIdentifiers[$resource][$field] = array_unique($values);
            }
        }

        return $changedIdentifiers;
    }
}
