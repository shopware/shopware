<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0
 *
 * The Admin API sync endpoint (`POST /api/_action/sync`) as an MCP tool: several upsert and delete
 * operations, on one or several entities, written by `SyncService` in one call.
 *
 * Builds its `McpToolResult` directly. Once the `*Result()` helpers of `McpToolResponse` exist
 * (#21306), they replace the private helpers here.
 */
#[Package('framework')]
#[McpTool(
    name: 'shopware-entity-sync',
    title: 'Entity Sync',
    description: 'Bulk write: run many upsert and delete operations, on one or several entities, in a single call, the same way as the Admin API sync endpoint. Use this, NOT shopware-entity-upsert or shopware-entity-delete, for imports and bulk changes (prices or stock of many products, many records at once), and when creates, updates and deletes on different entities belong to one change, e.g. replacing the categories of many products. For one or a few records of one entity, shopware-entity-upsert and shopware-entity-delete are simpler. Always use dryRun=true (default) first: it runs every operation, reports what would be written, deleted or not found, and changes nothing. Then call again with dryRun=false. Many-to-many links are removed by a delete on the mapping entity, e.g. "product_category" with payload [{"productId":"...","categoryId":"..."}].'
)]
#[McpToolDependsOn('shopware-entity-schema')]
#[McpToolGroup('entity')]
#[McpToolRequires(entityParam: 'entity', operations: ['create', 'update', 'delete'])]
class EntitySyncTool extends McpToolResponse
{
    /**
     * The values of the `indexing-behavior` header this tool accepts. `disable-indexing` is left out on
     * purpose: the written data would stay unindexed (no search, no SEO URLs, stale listing prices) until
     * someone runs a full reindex, which a model cannot do and does not know it has to.
     */
    private const SUPPORTED_INDEXING_BEHAVIORS = ['', EntityIndexerRegistry::USE_INDEXING_QUEUE];

    /**
     * @internal
     */
    public function __construct(
        private readonly SyncServiceInterface $syncService,
        private readonly DefinitionInstanceRegistry $registry,
        private readonly McpContextProvider $contextProvider,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(
        #[Schema(description: 'The operations as a JSON array, in the format of the Admin API sync endpoint. Each operation is {"entity": "product", "action": "upsert" or "delete", "payload": [...]} with an optional "key" that names it in errors. An upsert payload is a list of records: include "id" to UPDATE a record, omit it to CREATE one. A delete payload is a list of primary keys, e.g. [{"id":"..."}], or for a mapping entity every key field, e.g. [{"productId":"...","categoryId":"..."}]. A delete may use "criteria" instead of "payload", e.g. [{"type":"equals","field":"productId","value":"..."}], to delete every matching row. shopware-entity-schema lists the field names.')]
        string $operations,
        #[Schema(description: 'Run every operation and report the result, then roll back so nothing changes. Leave true first, then call again with false to write.')]
        bool $dryRun = true,
        #[Schema(description: 'How the written data is indexed, like the "indexing-behavior" header of the sync endpoint. Leave empty to index right away. "use-queue-indexing" indexes in the background through the message queue, which makes large imports faster. "disable-indexing" is not supported. Applies to dry runs too.')]
        string $indexingBehavior = '',
        #[Schema(description: 'Do not trigger Flow Builder flows for these writes, like the "sw-skip-trigger-flow" header. A dry run never triggers flows.')]
        bool $skipTriggerFlow = false,
    ): McpToolResult {
        if (!\in_array($indexingBehavior, self::SUPPORTED_INDEXING_BEHAVIORS, true)) {
            return McpToolResult::failure(
                \sprintf('Unsupported indexingBehavior "%s". Leave it empty to index right away, or use "%s" to index through the message queue. "%s" is not supported.', $indexingBehavior, EntityIndexerRegistry::USE_INDEXING_QUEUE, EntityIndexerRegistry::DISABLE_INDEXING),
                McpToolError::INVALID_ARGUMENTS,
            );
        }

        $context = $this->contextProvider->getContext();

        $syncOperations = $this->parseOperations($operations);
        if ($syncOperations instanceof McpToolResult) {
            return $syncOperations;
        }

        $missing = $this->missingPrivileges($syncOperations, $context);
        if ($missing !== []) {
            return McpToolResult::failure(\sprintf('Missing privilege: %s', implode(', ', $missing)), McpToolError::MISSING_PRIVILEGE);
        }

        foreach ($syncOperations as $operation) {
            if ($error = $this->validateDeleteKeys($operation, $context)) {
                return $error;
            }
        }

        // The tool's context is shared with the rest of the request; the flow state must not leak into it.
        $context = clone $context;
        if ($skipTriggerFlow) {
            $context->addState(Context::SKIP_TRIGGER_FLOW);
        }

        $behavior = new SyncBehavior($indexingBehavior !== '' ? $indexingBehavior : null);
        $meta = [
            'dryRun' => $dryRun,
            'operations' => \count($syncOperations),
            'indexingBehavior' => $indexingBehavior !== '' ? $indexingBehavior : 'default',
            'skipTriggerFlow' => $skipTriggerFlow,
        ];

        $sync = fn (): McpToolResult => $this->sync($syncOperations, $context, $behavior, $meta);

        if (!$dryRun) {
            return $sync();
        }

        return $this->dryRun($context, $sync);
    }

    /**
     * Runs the operation through executeWithDryRun(), the dry-run mechanism of every write tool, and keeps
     * its McpToolResult. executeWithDryRun() reports a throwable of the operation and a failed rollback
     * as the legacy error string, which becomes a failure here.
     *
     * @param \Closure(): McpToolResult $operation
     */
    private function dryRun(Context $context, \Closure $operation): McpToolResult
    {
        $result = null;
        $error = $this->executeWithDryRun($this->connection, $context, static function () use ($operation, &$result): string {
            $result = $operation();

            return '';
        });

        if ($error === '' && $result instanceof McpToolResult) {
            return $result;
        }

        $decoded = json_decode($error, true);

        return McpToolResult::failure(
            \is_array($decoded) && \is_string($decoded['error'] ?? null) ? $decoded['error'] : $error,
            \is_array($decoded) && \is_string($decoded['code'] ?? null) ? $decoded['code'] : McpToolError::TOOL_ERROR,
        );
    }

    /**
     * @param list<SyncOperation> $operations
     * @param array<string, mixed> $meta
     */
    private function sync(array $operations, Context $context, SyncBehavior $behavior, array $meta): McpToolResult
    {
        try {
            // Same scope as the Admin API sync endpoint, so write protection and ACL behave the same.
            $result = $context->scope(
                Context::CRUD_API_SCOPE,
                fn (Context $context): SyncResult => $this->syncService->sync($operations, $context, $behavior),
            );
        } catch (MissingPrivilegeException $e) {
            // Raised by the DAL for a privilege the upfront check can't see, e.g. a nested association write.
            $decoded = json_decode($e->getMessage(), true);
            $privileges = \is_array($decoded) && \is_array($decoded['missingPrivileges'] ?? null) ? $decoded['missingPrivileges'] : [];

            return McpToolResult::failure(\sprintf('Missing privilege: %s', implode(', ', $privileges)), McpToolError::MISSING_PRIVILEGE);
        } catch (ShopwareHttpException $e) {
            // Validation and write errors name the operation key and the field, so the caller can fix them.
            return McpToolResult::failure($e->getMessage(), McpToolError::INVALID_ARGUMENTS);
        }

        $data = [
            'written' => $result->getData(),
            'deleted' => $result->getDeleted(),
            'notFound' => $result->getNotFound(),
        ];

        return McpToolResult::success($data, $meta, $this->summary($data, $meta['dryRun'] === true));
    }

    /**
     * @param array{written: array<array<mixed>>, deleted: array<array<mixed>>, notFound: array<array<mixed>>} $data
     */
    private function summary(array $data, bool $dryRun): string
    {
        $count = static fn (array $grouped): int => array_sum(array_map('count', $grouped));

        $summary = \sprintf(
            '%s %d written, %d deleted, %d not found.',
            $dryRun ? 'Dry run, nothing was changed. Would be:' : 'Done:',
            $count($data['written']),
            $count($data['deleted']),
            $count($data['notFound']),
        );

        return $dryRun ? $summary . ' Call again with dryRun=false to write.' : $summary;
    }

    /**
     * @return list<SyncOperation>|McpToolResult
     */
    private function parseOperations(string $operations): array|McpToolResult
    {
        try {
            $decoded = json_decode($operations, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return McpToolResult::failure(\sprintf('Invalid JSON for "operations": %s', $e->getMessage()), McpToolError::INVALID_ARGUMENTS);
        }

        if (!\is_array($decoded) || $decoded === []) {
            return McpToolResult::failure('"operations" must be a non-empty JSON array of sync operations.', McpToolError::INVALID_ARGUMENTS);
        }

        $syncOperations = [];
        foreach ($decoded as $key => $operation) {
            if (!\is_array($operation)) {
                return McpToolResult::failure(\sprintf('Operation "%s" must be an object with "entity", "action" and "payload".', $key), McpToolError::INVALID_ARGUMENTS);
            }

            try {
                $syncOperation = SyncOperation::createFromArray($operation, (string) $key);
            } catch (ShopwareHttpException $e) {
                return McpToolResult::failure($e->getMessage(), McpToolError::INVALID_ARGUMENTS);
            }

            $entity = $syncOperation->getEntity();
            if (!$this->registry->has($entity)) {
                return McpToolResult::failure(\sprintf('Operation "%s": entity "%s" not found. Use the shopware://entities resource for available entity names.', $syncOperation->getKey(), $entity), McpToolError::INVALID_ARGUMENTS);
            }

            foreach ($syncOperation->getPayload() as $row) {
                if (!\is_array($row)) {
                    return McpToolResult::failure(\sprintf('Operation "%s": "payload" must be a list of objects, e.g. [{"id":"..."}].', $syncOperation->getKey()), McpToolError::INVALID_ARGUMENTS);
                }
            }

            $syncOperations[] = $syncOperation;
        }

        return $syncOperations;
    }

    /**
     * The privileges per operation and entity, checked before anything is written, like the single-entity
     * write tools do: a record with "id" needs `update`, one without needs `create`, a delete needs `delete`.
     * The DAL's ACL check still runs on every write command, including nested associations.
     *
     * @param list<SyncOperation> $operations
     *
     * @return list<string> every missing privilege, so the caller can fix the role in one go
     */
    private function missingPrivileges(array $operations, Context $context): array
    {
        $required = [];
        foreach ($operations as $operation) {
            $entity = $operation->getEntity();

            if ($operation->getAction() === SyncOperation::ACTION_DELETE) {
                $required[$entity . ':delete'] = true;

                continue;
            }

            foreach ($operation->getPayload() as $row) {
                $required[$entity . (\is_array($row) && isset($row['id']) ? ':update' : ':create')] = true;
            }
        }

        return array_values(array_filter(
            array_keys($required),
            static fn (string $privilege): bool => !$context->isAllowed($privilege),
        ));
    }

    /**
     * Delete rows of a mapping entity name every key field, as in shopware-entity-delete (#20869). Version
     * fields of the key default to the context version in the DAL, which is the live version in a live
     * context. In a versioned context the tool refuses to guess, because a mapping row does not say which
     * side is versioned, and a wrong guess matches no row and removes nothing without an error.
     */
    private function validateDeleteKeys(SyncOperation $operation, Context $context): ?McpToolResult
    {
        if ($operation->getAction() !== SyncOperation::ACTION_DELETE || $operation->hasCriteria()) {
            return null;
        }

        $definition = $this->registry->getByEntityName($operation->getEntity());
        [$keyFields, $versionFields] = $this->primaryKeyFields($definition);

        if (\count($keyFields) <= 1) {
            return null;
        }

        $usage = \sprintf(
            'Operation "%s": entity "%s" has a composite primary key. Name %s in every payload row, e.g. %s.',
            $operation->getKey(),
            $definition->getEntityName(),
            implode(' and ', $keyFields),
            Json::encode([array_fill_keys($keyFields, '...')]),
        );

        foreach ($operation->getPayload() as $row) {
            foreach ($keyFields as $keyField) {
                $value = \is_array($row) ? ($row[$keyField] ?? null) : null;
                if (!\is_string($value) || $value === '') {
                    return McpToolResult::failure($usage, McpToolError::INVALID_ARGUMENTS);
                }
                if (!Uuid::isValid($value)) {
                    return McpToolResult::failure(\sprintf('"%s" must be a UUID. %s', $keyField, $usage), McpToolError::INVALID_ARGUMENTS);
                }
            }

            foreach ($versionFields as $versionField) {
                $version = $row[$versionField] ?? null;
                if ($version !== null) {
                    if (!\is_string($version) || !Uuid::isValid($version)) {
                        return McpToolResult::failure(\sprintf('Operation "%s": "%s" must be a version UUID.', $operation->getKey(), $versionField), McpToolError::INVALID_ARGUMENTS);
                    }

                    continue;
                }

                if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
                    return McpToolResult::failure(\sprintf(
                        'Operation "%s": this request runs in version %s. Name every version field (%s) in each payload row, for example the live version %s for a side that is not being edited.',
                        $operation->getKey(),
                        $context->getVersionId(),
                        implode(', ', $versionFields),
                        Defaults::LIVE_VERSION,
                    ), McpToolError::INVALID_ARGUMENTS);
                }
            }
        }

        return null;
    }

    /**
     * @return array{list<string>, list<string>} the key fields and the version fields of the primary key
     */
    private function primaryKeyFields(EntityDefinition $definition): array
    {
        $keyFields = [];
        $versionFields = [];
        foreach ($definition->getPrimaryKeys() as $field) {
            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                $versionFields[] = $field->getPropertyName();

                continue;
            }

            $keyFields[] = $field->getPropertyName();
        }

        return [$keyFields, $versionFields];
    }
}
