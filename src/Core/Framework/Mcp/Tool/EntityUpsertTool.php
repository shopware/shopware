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
                return json_encode(['error' => \sprintf('Missing privilege: %s:%s', $entity, $privilege)], \JSON_THROW_ON_ERROR);
            }
        }

        $repository = $this->registry->getRepository($entity);

        $data = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($data)) {
            return json_encode(['error' => 'Payload must be a JSON object or array of objects.'], \JSON_THROW_ON_ERROR);
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

                return json_encode([
                    'dryRun' => true,
                    'success' => true,
                    'preview' => $written,
                ], \JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                return json_encode([
                    'dryRun' => true,
                    'success' => false,
                    'error' => $e->getMessage(),
                ], \JSON_THROW_ON_ERROR);
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

        return json_encode([
            'dryRun' => false,
            'success' => true,
            'written' => $written,
        ], \JSON_THROW_ON_ERROR);
    }
}
