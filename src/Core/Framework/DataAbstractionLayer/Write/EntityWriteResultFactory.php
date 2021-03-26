<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\Uuid\Uuid;

class EntityWriteResultFactory
{
    public function build(WriteCommandQueue $queue): array
    {
        $results = $this->buildQueueResults($queue);

        return $results;
    }

    private function buildQueueResults(WriteCommandQueue $queue): array
    {
        $identifiers = [];

        $order = [];
        // we have to create the written events in the written order, otherwise the version manager would
        // trace the change sets in a wrong order
        foreach ($queue->getCommandsInOrder() as $command) {
            $class = $command->getDefinition()->getClass();
            if (isset($order[$class])) {
                continue;
            }
            $order[$class] = $command->getDefinition();
        }

        foreach ($order as $class => $definition) {
            $commands = $queue->getCommands()[$class];

            if (\count($commands) === 0) {
                continue;
            }

            $primaryKeys = $definition->getPrimaryKeys()
                ->filter(static function (Field $field) {
                    return !$field instanceof VersionField && !$field instanceof ReferenceVersionField;
                });

            $identifiers[$definition->getEntityName()] = [];

            $jsonUpdateCommands = [];
            $writeResults = [];

            foreach ($commands as $command) {
                $primaryKey = $this->getCommandPrimaryKey($command, $primaryKeys);
                $uniqueId = \is_array($primaryKey) ? implode('-', $primaryKey) : $primaryKey;

                if ($command instanceof JsonUpdateCommand) {
                    $jsonUpdateCommands[$uniqueId] = $command;

                    continue;
                }

                $operation = EntityWriteResult::OPERATION_UPDATE;
                if ($command instanceof InsertCommand) {
                    $operation = EntityWriteResult::OPERATION_INSERT;
                } elseif ($command instanceof DeleteCommand) {
                    $operation = EntityWriteResult::OPERATION_DELETE;
                }

                $payload = $this->getCommandPayload($command);
                $writeResults[$uniqueId] = new EntityWriteResult(
                    $primaryKey,
                    $payload,
                    $command->getDefinition()->getEntityName(),
                    $operation,
                    $command->getEntityExistence(),
                    $command instanceof ChangeSetAware ? $command->getChangeSet() : null
                );
            }

            /*
             * Updates for entities with attributes are split into two commands: an UpdateCommand and a JsonUpdateCommand.
             * We need to merge the payloads here.
             */
            foreach ($jsonUpdateCommands as $uniqueId => $command) {
                $payload = [];
                if (isset($writeResults[$uniqueId])) {
                    $payload = $writeResults[$uniqueId]->getPayload();
                }

                /** @var Field&StorageAware $field */
                $field = $command->getDefinition()->getFields()->getByStorageName($command->getStorageName());

                $decodedPayload = $field->getSerializer()->decode(
                    $field,
                    json_encode($command->getPayload(), JSON_PRESERVE_ZERO_FRACTION)
                );
                $mergedPayload = array_merge($payload, [$field->getPropertyName() => $decodedPayload]);

                $changeSet = [];
                if ($command instanceof ChangeSetAware) {
                    $changeSet = $command->getChangeSet();
                }

                $writeResults[$uniqueId] = new EntityWriteResult(
                    $this->getCommandPrimaryKey($command, $primaryKeys),
                    $mergedPayload,
                    $command->getDefinition()->getEntityName(),
                    EntityWriteResult::OPERATION_UPDATE,
                    $command->getEntityExistence(),
                    $changeSet
                );
            }

            $identifiers[$definition->getEntityName()] = array_values($writeResults);
        }

        return $identifiers;
    }

    /**
     * @return array|string
     */
    private function getCommandPrimaryKey(WriteCommand $command, FieldCollection $fields)
    {
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

    /**
     * @throws \RuntimeException
     */
    private function getCommandPayload(WriteCommand $command): array
    {
        $payload = [];
        if ($command instanceof InsertCommand || $command instanceof UpdateCommand) {
            $payload = $command->getPayload();
        }

        $fields = $command->getDefinition()->getFields();

        $convertedPayload = [];
        foreach ($payload as $key => $value) {
            $field = $fields->getByStorageName($key);

            if (!$field) {
                continue;
            }

            $convertedPayload[$field->getPropertyName()] = $field->getSerializer()->decode($field, $value);
        }

        $primaryKeys = $command->getDefinition()->getPrimaryKeys();

        /** @var Field|StorageAware $primaryKey */
        foreach ($primaryKeys as $primaryKey) {
            if (\array_key_exists($primaryKey->getPropertyName(), $payload)) {
                continue;
            }

            if (!\array_key_exists($primaryKey->getStorageName(), $command->getPrimaryKey())) {
                throw new \RuntimeException(
                    sprintf(
                        'Primary key field %s::%s not found in payload or command primary key',
                        $command->getDefinition()->getClass(),
                        $primaryKey->getStorageName()
                    )
                );
            }

            $key = $command->getPrimaryKey()[$primaryKey->getStorageName()];

            $convertedPayload[$primaryKey->getPropertyName()] = Uuid::fromBytesToHex($key);
        }

        return $convertedPayload;
    }
}
