<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\Api\Exception\IncompletePrimaryKeyException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolation;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Language\LanguageLoaderInterface;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Handles all write operations in the system.
 * Builds first a command queue over the WriteCommandExtractor and let execute this queue
 * over the EntityWriteGateway (sql implementation in default).
 */
class EntityWriter implements EntityWriterInterface
{
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

    /**
     * @var LanguageLoaderInterface
     */
    private $languageLoader;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    public function __construct(
        WriteCommandExtractor $writeResource,
        EntityForeignKeyResolver $foreignKeyResolver,
        EntityWriteGatewayInterface $gateway,
        LanguageLoaderInterface $languageLoader,
        DefinitionInstanceRegistry $registry
    ) {
        $this->foreignKeyResolver = $foreignKeyResolver;
        $this->writeResource = $writeResource;
        $this->gateway = $gateway;
        $this->languageLoader = $languageLoader;
        $this->registry = $registry;
    }

    public function upsert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->write($definition, $rawData, $writeContext);
    }

    public function insert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->write($definition, $rawData, $writeContext, InsertCommand::class);
    }

    public function update(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->write($definition, $rawData, $writeContext, UpdateCommand::class);
    }

    /**
     * @param array[] $ids
     *
     * @throws IncompletePrimaryKeyException
     * @throws RestrictDeleteViolationException
     */
    public function delete(EntityDefinition $definition, array $ids, WriteContext $writeContext): DeleteResult
    {
        $this->validateWriteInput($ids);

        $commandQueue = new WriteCommandQueue();

        $fields = $definition->getPrimaryKeys();

        $resolved = [];
        foreach ($ids as $raw) {
            $mapped = [];

            /** @var Field $field */
            foreach ($fields as $field) {
                if (!($field instanceof StorageAware)) {
                    continue;
                }

                if (\array_key_exists($field->getPropertyName(), $raw)) {
                    $mapped[$field->getStorageName()] = $raw[$field->getPropertyName()];
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

                $fieldKeys = $fields
                    ->filter(function (Field $field) {
                        return !$field instanceof VersionField && !$field instanceof ReferenceVersionField;
                    })
                    ->map(function (Field $field) {
                        return $field->getPropertyName();
                    });

                throw new IncompletePrimaryKeyException($fieldKeys);
            }

            $resolved[] = $mapped;
        }

        if (!$definition instanceof MappingEntityDefinition) {
            $restrictions = $this->foreignKeyResolver->getAffectedDeleteRestrictions($definition, $resolved, $writeContext->getContext());

            if (!empty($restrictions)) {
                $restrictions = array_map(function ($restriction) {
                    return new RestrictDeleteViolation($restriction['pk'], $restriction['restrictions']);
                }, $restrictions);

                throw new RestrictDeleteViolationException($definition, $restrictions, $this->registry);
            }
        }

        $cascades = [];
        if (!$definition instanceof MappingEntityDefinition) {
            $cascadeDeletes = $this->foreignKeyResolver->getAffectedDeletes($definition, $resolved, $writeContext->getContext());

            $cascadeDeletes = array_column($cascadeDeletes, 'restrictions');
            foreach ($cascadeDeletes as $cascadeDelete) {
                $cascades = array_merge_recursive($cascades, $cascadeDelete);
            }

            foreach ($cascades as $affectedDefinitionClass => &$cascade) {
                $affectedDefinition = $this->registry->get($affectedDefinitionClass);

                $cascade = array_map(function ($key) use ($affectedDefinition) {
                    $payload = $key;

                    if (!\is_array($key)) {
                        $payload = ['id' => $key];
                    }

                    return new EntityWriteResult(
                        $key,
                        $payload,
                        $affectedDefinition->getEntityName(),
                        null
                    );
                }, $cascade);
            }
        }

        $skipped = [];
        foreach ($resolved as $mapped) {
            $mappedBytes = array_map(function ($id) {
                return Uuid::fromHexToBytes($id);
            }, $mapped);

            $existence = $this->gateway->getExistence($definition, $mappedBytes, [], $commandQueue);

            if (!$existence->exists()) {
                $skipped[$definition->getClass()][] = new EntityWriteResult($mapped, $mapped, $definition->getEntityName(), $existence);
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

        $writeContext->setLanguages($this->languageLoader->loadLanguages());
        $identifiers = $this->getWriteResults($commandQueue);
        $this->gateway->execute($commandQueue->getCommandsInOrder(), $writeContext);

        return new DeleteResult(
            array_merge_recursive($identifiers, $cascades),
            $skipped
        );
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidUuidLengthException
     */
    private function getWriteResults(WriteCommandQueue $queue): array
    {
        $identifiers = [];

        /** @var WriteCommandInterface[] $commands */
        foreach ($queue->getCommands() as $commands) {
            if (\count($commands) === 0) {
                continue;
            }

            //@todo@jp fix data format
            $definition = $commands[0]->getDefinition();

            $primaryKeys = $definition->getPrimaryKeys()
                ->filter(static function (Field $field) {
                    return !$field instanceof VersionField && !$field instanceof ReferenceVersionField;
                });

            $identifiers[$definition->getClass()] = [];

            $jsonUpdateCommands = [];
            $writeResults = [];

            foreach ($commands as $command) {
                $primaryKey = $this->getCommandPrimaryKey($command, $primaryKeys);
                /** @var string $uniqueId */
                $uniqueId = \is_array($primaryKey) ? implode('-', $primaryKey) : $primaryKey;

                if ($command instanceof JsonUpdateCommand) {
                    $jsonUpdateCommands[$uniqueId] = $command;
                    continue;
                }

                $payload = $this->getCommandPayload($command);
                $writeResults[$uniqueId] = new EntityWriteResult(
                    $primaryKey,
                    $payload,
                    $command->getDefinition()->getEntityName(),
                    $command->getEntityExistence()
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

                $field = $command->getDefinition()->getFields()->getByStorageName($command->getStorageName());
                $decodedPayload = $field->getSerializer()->decode(
                    $field,
                    json_encode($command->getPayload(), JSON_PRESERVE_ZERO_FRACTION)
                );
                $mergedPayload = array_merge($payload, [$command->getStorageName() => $decodedPayload]);

                $writeResults[$uniqueId] = new EntityWriteResult(
                    $this->getCommandPrimaryKey($command, $primaryKeys),
                    $mergedPayload,
                    $command->getDefinition()->getEntityName(),
                    $command->getEntityExistence()
                );
            }

            $identifiers[$definition->getClass()] = array_values($writeResults);
        }

        return $identifiers;
    }

    private function write(
        EntityDefinition $definition,
        array $rawData,
        WriteContext $writeContext,
        ?string $ensure = null
    ): array {
        $this->validateWriteInput($rawData);

        $commandQueue = new WriteCommandQueue();
        $parameters = new WriteParameterBag($definition, $writeContext, '', $commandQueue);

        $writeContext->setLanguages($this->languageLoader->loadLanguages());

        foreach ($rawData as $index => $row) {
            $parameters->setPath('/' . $index);
            $writeContext->resetPaths();
            $this->writeResource->extract($row, $parameters);
        }

        if ($ensure) {
            $commandQueue->ensureIs($definition, $ensure);
        }

        $writeContext->getExceptions()->tryToThrow();

        $writeIdentifiers = $this->getWriteResults($commandQueue);
        $this->gateway->execute($commandQueue->getCommandsInOrder(), $writeContext);

        return $writeIdentifiers;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function validateWriteInput(array $data): void
    {
        $valid = array_keys($data) === range(0, \count($data) - 1);

        if (!$valid) {
            throw new \InvalidArgumentException('Expected input to be non empty non associative array.');
        }
    }

    /**
     * @return array|string
     */
    private function getCommandPrimaryKey(WriteCommandInterface $command, FieldCollection $fields)
    {
        $primaryKey = $command->getPrimaryKey();

        $data = [];

        if ($fields->count() === 1) {
            /** @var StorageAware|Field $field */
            $field = $fields->first();

            return Uuid::fromBytesToHex($primaryKey[$field->getStorageName()]);
        }

        foreach ($fields as $field) {
            $data[$field->getPropertyName()] = Uuid::fromBytesToHex($primaryKey[$field->getStorageName()]);
        }

        return $data;
    }

    /**
     * @throws \RuntimeException
     */
    private function getCommandPayload(WriteCommandInterface $command): array
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
