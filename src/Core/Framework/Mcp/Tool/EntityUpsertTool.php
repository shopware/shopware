<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-entity-upsert', description: 'Create or update Shopware entity data. Always use dryRun=true (default) first to validate, then set dryRun=false to persist. Use shopware-entity-schema to understand required fields before building the payload. Returns validation result in dryRun mode, or the written entity data on commit.')]
#[Package('framework')]
class EntityUpsertTool
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

    public function __invoke(string $entity, string $payload, bool $dryRun = true): string
    {
        $context = $this->contextProvider->getContext();

        foreach (['create', 'update'] as $privilege) {
            if (!$context->isAllowed($entity . ':' . $privilege)) {
                return $this->error(\sprintf('Missing privilege: %s:%s', $entity, $privilege));
            }
        }

        $data = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($data)) {
            return $this->error('Payload must be a JSON object or array of objects.');
        }

        if (!\array_is_list($data)) {
            $data = [$data];
        }

        $repository = $this->registry->getRepository($entity);

        if ($dryRun) {
            $this->connection->beginTransaction();

            try {
                $events = $repository->upsert($data, $context);

                return $this->success($this->formatWriteResult($events), ['dryRun' => true]);
            } catch (\Throwable $e) {
                return $this->error($e->getMessage());
            } finally {
                $this->connection->rollBack();
            }
        }

        $events = $repository->upsert($data, $context);

        return $this->success($this->formatWriteResult($events), ['dryRun' => false]);
    }

    /**
     * @return list<array{entity: string, ids: list<string>, operation: string}>
     */
    private function formatWriteResult(EntityWrittenContainerEvent $events): array
    {
        $written = [];
        foreach ($events->getEvents()?->getElements() ?? [] as $event) {
            $written[] = [
                'entity' => $event->getEntityName(),
                'ids' => $event->getIds(),
                'operation' => 'upsert',
            ];
        }

        return $written;
    }
}
