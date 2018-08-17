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

namespace Shopware\Core\Framework\ORM\Write;

use Shopware\Core\Framework\Api\Exception\IncompletePrimaryKeyException;
use Shopware\Core\Framework\ORM\Dbal\EntityForeignKeyResolver;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\JsonField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\MappingEntityDefinition;
use Shopware\Core\Framework\ORM\Write\Command\DeleteCommand;
use Shopware\Core\Framework\ORM\Write\Command\InsertCommand;
use Shopware\Core\Framework\ORM\Write\Command\UpdateCommand;
use Shopware\Core\Framework\ORM\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\ORM\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\ORM\Write\FieldAware\DefaultExtender;
use Shopware\Core\Framework\ORM\Write\FieldAware\FieldExtenderCollection;
use Shopware\Core\Framework\ORM\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\ORM\Write\FieldException\FieldExceptionStack;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Validation\RestrictDeleteViolation;
use Shopware\Core\Framework\ORM\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Struct\Uuid;

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
     * @param string[]                $ids
     * @param WriteContext            $writeContext
     *
     * @throws RestrictDeleteViolationException
     * @throws IncompletePrimaryKeyException
     *
     * @return DeleteResult
     */
    public function delete(string $definition, array $ids, WriteContext $writeContext): DeleteResult
    {
        $this->validateWriteInput($ids);

        $commandQueue = new WriteCommandQueue();
        $commandQueue->setOrder($definition, ...$definition::getWriteOrder());

        /** @var FieldCollection $fields */
        $fields = $definition::getPrimaryKeys();
        $primaryKeyFields = [];

        $resolved = [];
        foreach ($ids as $raw) {
            $mapped = [];

            foreach ($fields as $field) {
                if (!($field instanceof StorageAware)) {
                    continue;
                }

                if (array_key_exists($field->getPropertyName(), $raw)) {
                    $mapped[$field->getStorageName()] = $raw[$field->getPropertyName()];
                    $primaryKeyFields[$field->getPropertyName()] = $raw[$field->getPropertyName()];
                    continue;
                }

                if ($field instanceof ReferenceVersionField) {
                    $mapped[$field->getStorageName()] = $writeContext->getContext()->getVersionId();
                    continue;
                }

                if ($field instanceof VersionField) {
                    $mapped[$field->getStorageName()] = $writeContext->getContext()->getVersionId();
                    continue;
                }

                if ($field instanceof TenantIdField) {
                    $mapped[$field->getStorageName()] = $writeContext->getContext()->getTenantId();
                    continue;
                }

                $fieldKeys = $fields
                    ->filter(function (Field $field) {
                        return !$field instanceof VersionField && !$field instanceof ReferenceVersionField && !$field instanceof TenantIdField;
                    })
                    ->map(function (Field $field) {
                        return $field->getPropertyName();
                    });

                throw new IncompletePrimaryKeyException($fieldKeys);
            }

            $resolved[] = $mapped;
        }

        $instance = new $definition();
        if (!$instance instanceof MappingEntityDefinition) {
            $restrictions = $this->foreignKeyResolver->getAffectedDeleteRestrictions($definition, $resolved, $writeContext->getContext());

            if (!empty($restrictions)) {
                $restrictions = array_map(function ($restriction) {
                    return new RestrictDeleteViolation($restriction['pk'], $restriction['restrictions']);
                }, $restrictions);

                throw new RestrictDeleteViolationException($definition, $restrictions);
            }
        }

        $cascades = [];
        if (!$instance instanceof MappingEntityDefinition) {
            $cascadeDeletes = $this->foreignKeyResolver->getAffectedDeletes($definition, $resolved, $writeContext->getContext());

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

        $skipped = [];
        foreach ($resolved as $mapped) {
            $mappedBytes = array_map(function ($id) {
                return Uuid::fromStringToBytes($id);
            }, $mapped);

            $existence = $this->gateway->getExistence($definition, $mappedBytes, [], $commandQueue);

            if (!$existence->exists()) {
                $skipped[$definition][] = [
                    'primaryKey' => $mapped,
                    'payload' => $mapped,
                    'existence' => $existence,
                ];
                continue;
            }

            $commandQueue->add(
                $definition,
                new DeleteCommand(
                    $definition,
                    $mappedBytes,
                    $existence
                )
            );
        }

        $identifiers = $this->getWriteIdentifiers($commandQueue);
        $this->gateway->execute($commandQueue->getCommandsInOrder());

        return new DeleteResult(
            array_merge_recursive($identifiers, $cascades),
            $skipped
        );
    }

    private function getWriteIdentifiers(WriteCommandQueue $queue): array
    {
        $identifiers = [];

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
                    'existence' => $command->getEntityExistence(),
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
        $payload = [];
        if ($command instanceof InsertCommand || $command instanceof UpdateCommand) {
            $payload = $command->getPayload();
        }

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

            if ($field instanceof JsonField && !empty($value)) {
                $value = json_decode($value, true);
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
