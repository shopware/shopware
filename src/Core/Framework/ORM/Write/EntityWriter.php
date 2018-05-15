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

namespace Shopware\Framework\ORM\Write;

use Shopware\Framework\ORM\Dbal\EntityForeignKeyResolver;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Field\Field;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\MappingEntityDefinition;
use Shopware\Framework\ORM\Write\Command\DeleteCommand;
use Shopware\Framework\ORM\Write\Command\InsertCommand;
use Shopware\Framework\ORM\Write\Command\UpdateCommand;
use Shopware\Framework\ORM\Write\Command\WriteCommandInterface;
use Shopware\Framework\ORM\Write\Command\WriteCommandQueue;
use Shopware\Framework\ORM\Write\FieldAware\DefaultExtender;
use Shopware\Framework\ORM\Write\FieldAware\FieldExtenderCollection;
use Shopware\Framework\ORM\Write\FieldAware\StorageAware;
use Shopware\Framework\ORM\Write\FieldException\FieldExceptionStack;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Validation\RestrictDeleteViolation;
use Shopware\Framework\ORM\Write\Validation\RestrictDeleteViolationException;
use Shopware\Framework\Struct\Uuid;

/**
 * Handles all write operations in the system.
 * Builds first a command queue over the WriteCommandExtractor and let execute this queue
 * over the EntityWriteGateway (sql implementation in default).
 */
class EntityWriter implements EntityWriterInterface
{
    /**
     * @var DefaultExtender
     */
    private $defaultExtender;

    /**
     * @var EntityForeignKeyResolver
     */
    private $foreignKeyResolver;

    /**
     * @var WriteCommandExtractor
     */
    private $writeResource;

    /**
     * @var EntityWriteGatewayInterface
     */
    private $gateway;

    public function __construct(
        WriteCommandExtractor $writeResource,
        DefaultExtender $defaultExtender,
        EntityForeignKeyResolver $foreignKeyResolver,
        EntityWriteGatewayInterface $gateway
    ) {
        $this->defaultExtender = $defaultExtender;
        $this->foreignKeyResolver = $foreignKeyResolver;
        $this->writeResource = $writeResource;
        $this->gateway = $gateway;
    }

    public function upsert(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $this->validateWriteInput($rawData);

        $commandQueue = $this->buildCommandQueue($definition, $rawData, $writeContext);

        $writeIdentifiers = $this->getWriteIdentifiers($commandQueue);

        $this->gateway->execute($commandQueue->getCommandsInOrder());

        return $writeIdentifiers;
    }

    public function insert(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $this->validateWriteInput($rawData);

        $commandQueue = $this->buildCommandQueue($definition, $rawData, $writeContext);
        $writeIdentifiers = $this->getWriteIdentifiers($commandQueue);

        $commandQueue->ensureIs($definition, InsertCommand::class);
        $this->gateway->execute($commandQueue->getCommandsInOrder());

        return $writeIdentifiers;
    }

    public function update(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $this->validateWriteInput($rawData);

        $commandQueue = $this->buildCommandQueue($definition, $rawData, $writeContext);

        $writeIdentifiers = $this->getWriteIdentifiers($commandQueue);

        $commandQueue->ensureIs($definition, UpdateCommand::class);

        $this->gateway->execute($commandQueue->getCommandsInOrder());

        return $writeIdentifiers;
    }

    /**
     * @param EntityDefinition|string $definition
     * @param array                   $ids
     * @param WriteContext            $writeContext
     *
     * @throws RestrictDeleteViolationException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     * @return array
     */
    public function delete(string $definition, array $ids, WriteContext $writeContext): array
    {
        $this->validateWriteInput($ids);

        $commandQueue = new WriteCommandQueue();
        $commandQueue->setOrder($definition, ...$definition::getWriteOrder());

        $fields = $definition::getPrimaryKeys();

        $resolved = [];
        foreach ($ids as $raw) {
            $mapped = [];

            /** @var StorageAware|IdField $field */
            foreach ($fields as $field) {
                if (array_key_exists($field->getPropertyName(), $raw)) {
                    $mapped[$field->getStorageName()] = $raw[$field->getPropertyName()];
                    continue;
                }

                if ($field instanceof ReferenceVersionField) {
                    $mapped[$field->getStorageName()] = $writeContext->getApplicationContext()->getVersionId();
                    continue;
                }

                if ($field instanceof VersionField) {
                    $mapped[$field->getStorageName()] = $writeContext->getApplicationContext()->getVersionId();
                    continue;
                }

                if ($field instanceof TenantIdField) {
                    $mapped[$field->getStorageName()] = $writeContext->getApplicationContext()->getTenantId();
                    continue;
                }

                throw new \InvalidArgumentException(
                    sprintf('Missing primary key value %s for entity %s', $field->getPropertyName(), $definition::getEntityName())
                );
            }

            $resolved[] = $mapped;
        }

        $instance = new $definition();
        if (!$instance instanceof MappingEntityDefinition) {
            $restrictions = $this->foreignKeyResolver->getAffectedDeleteRestrictions($definition, $resolved, $writeContext->getApplicationContext());

            if (!empty($restrictions)) {
                $restrictions = array_map(function ($restriction) {
                    return new RestrictDeleteViolation($restriction['pk'], $restriction['restrictions']);
                }, $restrictions);

                throw new RestrictDeleteViolationException($definition, $restrictions);
            }
        }

        $cascades = [];
        if (!$instance instanceof MappingEntityDefinition) {
            $cascadeDeletes = $this->foreignKeyResolver->getAffectedDeletes($definition, $resolved, $writeContext->getApplicationContext());

            $cascadeDeletes = array_column($cascadeDeletes, 'restrictions');
            foreach ($cascadeDeletes as $cascadeDelete) {
                $cascades = array_merge_recursive($cascades, $cascadeDelete);
            }

            foreach ($cascades as &$cascade) {
                $cascade = array_map(function ($key) {
                    $payload = $key;

                    if (!is_array($key)) {
                        $payload = ['id' => $key];
                    }

                    return ['primaryKey' => $key, 'payload' => $payload];
                }, $cascade);
            }
        }

        foreach ($resolved as $mapped) {
            $mapped = array_map(function ($id) {
                return Uuid::fromStringToBytes($id);
            }, $mapped);

            $commandQueue->add($definition, new DeleteCommand($definition, $mapped));
        }

        $identifiers = $this->getWriteIdentifiers($commandQueue);
        $this->gateway->execute($commandQueue->getCommandsInOrder());

        return array_merge_recursive($identifiers, $cascades);
    }

    private function getWriteIdentifiers(WriteCommandQueue $queue): array
    {
        $identifiers = [];

        /*
         * @var string
         * @var UpdateCommand[]|InsertCommand[] $queries
         */
        foreach ($queue->getCommands() as $resource => $commands) {
            if (count($commands) === 0) {
                continue;
            }

            $identifiers[$resource] = [];
            /** @var WriteCommandInterface[] $commands */
            foreach ($commands as $command) {
                $primaryKey = $this->getCommandPrimaryKey($command);
                $payload = $this->getCommandPayload($command);

                $identifiers[$resource][] = [
                    'primaryKey' => $primaryKey,
                    'payload' => $payload,
                ];
            }
        }

        return $identifiers;
    }

    private function buildCommandQueue(string $definition, array $rawData, WriteContext $writeContext): WriteCommandQueue
    {
        $commandQueue = new WriteCommandQueue();

        $extender = new FieldExtenderCollection();
        $extender->addExtender($this->defaultExtender);

        /* @var EntityDefinition $definition */
        $commandQueue->setOrder($definition, ...$definition::getWriteOrder());

        $commandQueue = new WriteCommandQueue();
        $exceptionStack = new FieldExceptionStack();

        foreach ($rawData as $row) {
            $writeContext->resetPaths();
            $this->writeResource->extract($row, $definition, $exceptionStack, $commandQueue, $writeContext, $extender);
        }
        $exceptionStack->tryToThrow();

        return $commandQueue;
    }

    private function validateWriteInput(array $data): void
    {
        $valid = array_keys($data) === range(0, count($data) - 1);

        if (!$valid) {
            throw new \InvalidArgumentException('Expected input to be array.');
        }
    }

    private function getCommandPrimaryKey(WriteCommandInterface $command)
    {
        $fields = $command->getDefinition()::getPrimaryKeys();
        $fields = $fields->filter(function (Field $field) {
            return !$field instanceof VersionField && !$field instanceof ReferenceVersionField && !$field instanceof TenantIdField;
        });

        $primaryKey = $command->getPrimaryKey();

        $data = [];

        if ($fields->count() === 1) {
            /** @var StorageAware|Field $field */
            $field = $fields->first();

            return Uuid::fromBytesToHex($primaryKey[$field->getStorageName()]);
        }

        /** @var StorageAware|Field $field */
        foreach ($fields as $field) {
            $data[$field->getPropertyName()] = Uuid::fromBytesToHex($primaryKey[$field->getStorageName()]);
        }

        return $data;
    }

    private function getCommandPayload(WriteCommandInterface $command): array
    {
        /** @var InsertCommand|UpdateCommand $command */
        $payload = $command instanceof DeleteCommand ? [] : $command->getPayload();

        $fields = $command->getDefinition()::getFields();

        $convertedPayload = [];
        foreach ($payload as $key => $value) {
            $field = $fields->getByStorageName($key);

            if (!$field) {
                continue;
            }

            if (($field instanceof IdField || $field instanceof FkField) && !empty($value)) {
                $value = Uuid::fromBytesToHex($value);
            }
            $convertedPayload[$field->getPropertyName()] = $value;
        }

        $primaryKeys = $fields->filterByFlag(PrimaryKey::class);

        /** @var Field|StorageAware $primaryKey */
        foreach ($primaryKeys as $primaryKey) {
            if (array_key_exists($primaryKey->getPropertyName(), $payload)) {
                continue;
            }

            if (!array_key_exists($primaryKey->getStorageName(), $command->getPrimaryKey())) {
                throw new \RuntimeException(
                    sprintf('Primary key field %s::%s not found in payload or command primary key', $command->getDefinition(), $primaryKey->getStorageName())
                );
            }

            $key = $command->getPrimaryKey()[$primaryKey->getStorageName()];

            $convertedPayload[$primaryKey->getPropertyName()] = Uuid::fromBytesToHex($key);
        }

        return $convertedPayload;
    }
}
