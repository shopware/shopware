<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-entity-upsert', title: 'Entity Upsert', description: 'Create or update Shopware entity data. Always use dryRun=true (default) first to validate, then set dryRun=false to persist. Use shopware-entity-schema to understand required fields before building the payload. Returns validation result in dryRun mode, or the written entity data on commit.')]
#[McpToolDependsOn('shopware-entity-schema')]
#[McpToolRequires(entityParam: 'entity', operations: ['create', 'update'])]
#[Package('framework')]
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

    public function __invoke(string $entity, string $payload, bool $dryRun = true): string
    {
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
