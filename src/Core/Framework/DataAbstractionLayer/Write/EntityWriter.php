<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\Api\Exception\IncompletePrimaryKeyException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ImpossibleWriteOrderException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\FieldExceptionStack;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolation;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageLoaderInterface;

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
     * @var FieldSerializerRegistry
     */
    private $fieldHandler;

    /**
     * @var LanguageLoaderInterface
     */
    private $languageLoader;

    public function __construct(
        WriteCommandExtractor $writeResource,
        EntityForeignKeyResolver $foreignKeyResolver,
        EntityWriteGatewayInterface $gateway,
        FieldSerializerRegistry $fieldHandler,
        LanguageLoaderInterface $languageLoader
    ) {
        $this->foreignKeyResolver = $foreignKeyResolver;
        $this->writeResource = $writeResource;
        $this->gateway = $gateway;
        $this->fieldHandler = $fieldHandler;
        $this->languageLoader = $languageLoader;
    }

    public function upsert(string $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->write($definition, $rawData, $writeContext);
    }

    public function insert(string $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->write($definition, $rawData, $writeContext, InsertCommand::class);
    }

    public function update(string $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->write($definition, $rawData, $writeContext, UpdateCommand::class);
    }

    /**
     * @param EntityDefinition|string $definition
     * @param array[]                 $ids
     *
     * @throws RestrictDeleteViolationException
     * @throws IncompletePrimaryKeyException
     * @throws ImpossibleWriteOrderException
     */
    public function delete(string $definition, array $ids, WriteContext $writeContext): DeleteResult
    {
        $this->validateWriteInput($ids);

        $commandQueue = new WriteCommandQueue();

        /** @var FieldCollection $fields */
        $fields = new FieldCollection($definition::getPrimaryKeys());

        $resolved = [];
        foreach ($ids as $raw) {
            $mapped = [];

            /** @var Field $field */
            foreach ($fields as $field) {
                if (!($field instanceof StorageAware)) {
                    continue;
                }

                if (array_key_exists($field->getPropertyName(), $raw)) {
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

                    if (!\is_array($key)) {
                        $payload = ['id' => $key];
                    }

                    return new EntityWriteResult($key, $payload, null);
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
                $skipped[$definition][] = new EntityWriteResult($mapped, $mapped, $existence);
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

    private function getWriteResults(WriteCommandQueue $queue): array
    {
        $identifiers = [];

        /** @var EntityDefinition|string $definition */
        foreach ($queue->getCommands() as $definition => $commands) {
            if (\count($commands) === 0) {
                continue;
            }

            $primaryKeys = (new FieldCollection($definition::getPrimaryKeys()))
                ->filter(function (Field $field) {
                    return !$field instanceof VersionField && !$field instanceof ReferenceVersionField;
                });

            $identifiers[$definition] = [];

            $jsonUpdateCommands = [];
            $writeResults = [];

            /** @var WriteCommandInterface[] $commands */
            foreach ($commands as $command) {
                $primaryKey = $this->getCommandPrimaryKey($command, $primaryKeys);
                $uniqueId = is_array($primaryKey) ? implode('-', $primaryKey) : $primaryKey;

                if ($command instanceof JsonUpdateCommand) {
                    $jsonUpdateCommands[$uniqueId] = $command;
                    continue;
                }

                $payload = $this->getCommandPayload($command);
                $writeResults[$uniqueId] = new EntityWriteResult($primaryKey, $payload, $command->getEntityExistence());
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

                $field = $command->getDefinition()::getFields()->getByStorageName($command->getStorageName());
                $decodedPayload = $this->fieldHandler->decode(
                    $field,
                    json_encode($command->getPayload(), JSON_PRESERVE_ZERO_FRACTION)
                );
                $mergedPayload = array_merge($payload, [$command->getStorageName() => $decodedPayload]);

                $writeResults[$uniqueId] = new EntityWriteResult(
                    $this->getCommandPrimaryKey($command, $primaryKeys),
                    $mergedPayload,
                    $command->getEntityExistence()
                );
            }

            $identifiers[$definition] = array_values($writeResults);
        }

        return $identifiers;
    }

    private function write(
        string $definition,
        array $rawData,
        WriteContext $writeContext,
        ?string $ensure = null
    ): array {
        $this->validateWriteInput($rawData);

        $commandQueue = new WriteCommandQueue();
        $exceptionStack = new FieldExceptionStack();

        $parameters = new WriteParameterBag($definition, $writeContext, '', $commandQueue, $exceptionStack);

        $writeContext->setLanguages($this->languageLoader->loadLanguages());

        foreach ($rawData as $row) {
            $writeContext->resetPaths();
            $this->writeResource->extract($row, $parameters);
        }

        /* @var string|EntityDefinition $definition */
        if ($ensure) {
            $commandQueue->ensureIs($definition, $ensure);
        }

        $exceptionStack->tryToThrow();

        $writeIdentifiers = $this->getWriteResults($commandQueue);
        $this->gateway->execute($commandQueue->getCommandsInOrder(), $writeContext);

        return $writeIdentifiers;
    }

    private function validateWriteInput(array $data): void
    {
        $valid = array_keys($data) === range(0, \count($data) - 1);

        if (!$valid) {
            throw new \InvalidArgumentException('Expected input to be non empty non associative array.');
        }
    }

    private function getCommandPrimaryKey(WriteCommandInterface $command, FieldCollection $fields)
    {
        $primaryKey = $command->getPrimaryKey();

        $data = [];

        if ($fields->count() === 1) {
            /** @var StorageAware|Field $field */
            $field = $fields->first();

            return Uuid::fromBytesToHex($primaryKey[$field->getStorageName()]);
        }

        /** @var StorageAware[]|Field[] $fields */
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

            $convertedPayload[$field->getPropertyName()] = $this->fieldHandler->decode($field, $value);
        }

        $primaryKeys = $command->getDefinition()::getPrimaryKeys();

        /** @var Field|StorageAware $primaryKey */
        foreach ($primaryKeys as $primaryKey) {
            if (array_key_exists($primaryKey->getPropertyName(), $payload)) {
                continue;
            }

            if (!array_key_exists($primaryKey->getStorageName(), $command->getPrimaryKey())) {
                throw new \RuntimeException(
                    sprintf(
                        'Primary key field %s::%s not found in payload or command primary key',
                        $command->getDefinition(),
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
