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
#[McpTool(name: 'shopware-entity-upsert', description: 'Create or update Shopware entity data. Pass the entity name and a JSON payload. Set dryRun=true (default) to validate without persisting.')]
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

        $repository = $this->registry->getRepository($entity);

        $data = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($data)) {
            return $this->error('Payload must be a JSON object or array of objects.');
        }

        if (!\array_is_list($data)) {
            $data = [$data];
        }

        if ($dryRun) {
            $this->connection->beginTransaction();

            try {
                $events = $repository->upsert($data, $context);

                $written = [];
                foreach ($events->getEvents()?->getElements() ?? [] as $event) {
                    $written[] = [
                        'entity' => $event->getEntityName(),
                        'ids' => $event->getIds(),
                        'operation' => 'upsert',
                    ];
                }

                return $this->success($written, ['dryRun' => true]);
            } catch (\Throwable $e) {
                return $this->error($e->getMessage());
            } finally {
                $this->connection->rollBack();
            }
        }

        $events = $repository->upsert($data, $context);

        $written = [];
        foreach ($events->getEvents()?->getElements() ?? [] as $event) {
            $written[] = [
                'entity' => $event->getEntityName(),
                'ids' => $event->getIds(),
                'operation' => 'upsert',
            ];
        }

        return $this->success($written, ['dryRun' => false]);
    }
}
