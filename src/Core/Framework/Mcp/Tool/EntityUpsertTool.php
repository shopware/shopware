<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpTool(
    name: 'shopware-entity-upsert',
    title: 'Entity Upsert',
    description: 'Create or update Shopware entity data. Always use dryRun=true (default) first to validate, then set dryRun=false to persist. If you don\'t already know the required fields, shopware-entity-schema will tell you. Returns validation result in dryRun mode, or the written entity data on commit.'
)]
#[McpToolDependsOn('shopware-entity-schema')]
#[McpToolGroup('entity')]
#[McpToolRequires(entityParam: 'entity', operations: ['create', 'update'])]
class EntityUpsertTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly McpContextProvider $contextProvider,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(
        #[Schema(description: 'Entity name to write, e.g. "product" or "category". See the shopware://entities resource for the full list.')]
        string $entity,
        #[Schema(description: 'The entity\'s fields as a JSON string: one OBJECT, or an ARRAY of objects to write several records in a single call. Include "id" on a record to UPDATE it, omit it to CREATE one — e.g. {"id":"...","name":"Summer Sale"} renames an existing category, and [{...},{...}] upserts both. shopware-entity-schema lists the field names and which are required.')]
        string $payload,
        #[Schema(description: 'Validate without writing. Leave true first, then call again with false to persist.')]
        bool $dryRun = true,
    ): string {
        $context = $this->contextProvider->getContext();

        if (!$this->registry->has($entity)) {
            return $this->error(\sprintf('Entity "%s" not found. Use the shopware://entities resource for available entity names.', $entity));
        }

        $data = $this->decodeJsonOrError($payload, 'payload');
        if (\is_string($data)) {
            return $data;
        }

        if (!\array_is_list($data)) {
            $data = [$data];
        }

        $needsCreate = false;
        $needsUpdate = false;
        foreach ($data as $item) {
            if (isset($item['id'])) {
                $needsUpdate = true;
            } else {
                $needsCreate = true;
            }
        }

        $privileges = [];
        if ($needsCreate) {
            $privileges[] = $entity . ':create';
        }
        if ($needsUpdate) {
            $privileges[] = $entity . ':update';
        }
        if ($privileges === []) {
            $privileges[] = $entity . ':create';
        }

        if ($error = $this->requirePrivilege($context, ...$privileges)) {
            return $error;
        }

        $repository = $this->registry->getRepository($entity);

        if ($dryRun) {
            return $this->executeWithDryRun($this->connection, $context, function () use ($repository, $data, $context) {
                $events = $repository->upsert($data, $context);

                return $this->success($this->formatWriteEvents($events, 'upsert'), ['dryRun' => true]);
            });
        }

        $events = $repository->upsert($data, $context);

        return $this->success($this->formatWriteEvents($events, 'upsert'), ['dryRun' => false]);
    }
}
