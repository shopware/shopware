<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(
    name: 'shopware-sync-validate',
    description: 'Validate a Sync API batch without writing to the database. Validates every record in parallel and returns per-record errors for the entire batch — does not abort on the first failure. Input: a JSON string matching the /api/_action/sync request body format (array of {action, entity, payload[]}). Returns {success, data: {validation_mode, total_invalid_records, operations: [...], note}}.',
)]
#[Package('framework')]
class SyncValidateTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly McpContextProvider $contextProvider,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param string $operations JSON string — array of {action: string, entity: string, payload: array[]}
     */
    public function __invoke(string $operations): string
    {
        try {
            $ops = json_decode($operations, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->error(\sprintf('Invalid JSON: %s', $e->getMessage()));
        }

        if (!\is_array($ops) || !\array_is_list($ops)) {
            return $this->error('Operations must be a JSON array of {action, entity, payload[]} objects.');
        }

        $context = $this->contextProvider->getContext();
        $results = [];
        $totalErrors = 0;

        foreach ($ops as $opIndex => $op) {
            if (!\is_array($op)) {
                $results[] = [
                    'operation_index' => $opIndex,
                    'error' => 'Each operation must be an object with entity and payload keys.',
                    'records' => [],
                ];
                ++$totalErrors;
                continue;
            }

            $entity = $op['entity'] ?? '';
            $payload = $op['payload'] ?? [];
            $action = $op['action'] ?? 'upsert';

            if ($entity === '' || !\is_array($payload)) {
                $results[] = [
                    'operation_index' => $opIndex,
                    'entity' => $entity,
                    'error' => 'Missing required fields: entity (string) and payload (array).',
                    'records' => [],
                ];
                ++$totalErrors;
                continue;
            }

            if (!\array_is_list($payload)) {
                $payload = [$payload];
            }

            try {
                $repository = $this->registry->getRepository($entity);
            } catch (\Throwable) {
                $results[] = [
                    'operation_index' => $opIndex,
                    'entity' => $entity,
                    'error' => \sprintf(
                        'Unknown entity: "%s". Use the shopware://entities resource for valid entity names.',
                        $entity,
                    ),
                    'records' => [],
                ];
                ++$totalErrors;
                continue;
            }

            $opResults = [];

            foreach ($payload as $recordIndex => $record) {
                if (!\is_array($record)) {
                    $opResults[] = [
                        'record_index' => $recordIndex,
                        'record_id' => \sprintf('index %d', $recordIndex),
                        'valid' => false,
                        'errors' => [['message' => 'Record must be an object, got ' . \gettype($record), 'hint' => null]],
                    ];
                    ++$totalErrors;
                    continue;
                }

                $recordErrors = [];

                $jsonResult = $this->executeWithDryRun(
                    $this->connection,
                    function () use ($repository, $record, $context): string {
                        $repository->upsert([$record], $context);

                        return $this->success(['validated' => true]);
                    },
                );

                $decoded = json_decode($jsonResult, true);
                if (isset($decoded['error'])) {
                    $recordErrors[] = [
                        'message' => $decoded['error'],
                        'hint' => $this->buildFixHint($decoded['error']),
                    ];
                }

                $isValid = $recordErrors === [];
                if (!$isValid) {
                    ++$totalErrors;
                }

                $recordId = $record['id'] ?? $record['productNumber'] ?? \sprintf('index %d', $recordIndex);
                $opResults[] = [
                    'record_index' => $recordIndex,
                    'record_id' => $recordId,
                    'valid' => $isValid,
                    'errors' => $recordErrors,
                ];
            }

            $validCount = \count(array_filter($opResults, static fn (array $r): bool => $r['valid']));
            $invalidCount = \count($opResults) - $validCount;

            $results[] = [
                'operation_index' => $opIndex,
                'entity' => $entity,
                'action' => $action,
                'total_records' => \count($payload),
                'valid_records' => $validCount,
                'invalid_records' => $invalidCount,
                'records' => $opResults,
                'summary' => $invalidCount === 0
                    ? \sprintf('All %d records are valid', $validCount)
                    : \sprintf('%d of %d records have errors', $invalidCount, \count($payload)),
            ];
        }

        return $this->success([
            'validation_mode' => 'dry_run_no_write',
            'operations_validated' => \count($results),
            'total_invalid_records' => $totalErrors,
            'note' => $totalErrors === 0
                ? 'All records are valid — safe to submit to /api/_action/sync'
                : 'Fix the errors listed above before submitting. No data was written to the database.',
            'operations' => $results,
        ]);
    }

    private function buildFixHint(string $errorMessage): ?string
    {
        if (str_contains($errorMessage, 'required')) {
            return 'This field is required — provide a value or check the entity schema with shopware-entity-schema.';
        }
        if (str_contains($errorMessage, 'uuid') || str_contains($errorMessage, 'UUID')) {
            return 'Field expects a valid UUID — use shopware-entity-search to look up the correct ID.';
        }
        if (str_contains($errorMessage, 'Foreign key') || str_contains($errorMessage, 'foreign key')) {
            return 'Referenced entity does not exist — verify with shopware-entity-search first.';
        }
        if (str_contains($errorMessage, 'NOT NULL') || str_contains($errorMessage, 'not null')) {
            return 'Field is not nullable — provide a value or remove the key from the record payload.';
        }

        return null;
    }
}
