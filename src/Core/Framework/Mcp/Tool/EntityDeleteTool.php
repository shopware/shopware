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
#[McpTool(name: 'shopware-entity-delete', description: 'Delete Shopware entities by their IDs. Set dryRun=true (default) to preview cascade effects without deleting.')]
#[Package('framework')]
class EntityDeleteTool
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly McpContextProvider $contextProvider,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(string $entity, string $ids, bool $dryRun = true): string
    {
        $context = $this->contextProvider->getContext();

        if (!$context->isAllowed($entity . ':delete')) {
            return json_encode(['error' => \sprintf('Missing privilege: %s:delete', $entity)], \JSON_THROW_ON_ERROR);
        }

        $repository = $this->registry->getRepository($entity);

        $idList = json_decode($ids, true);
        if (!\is_array($idList)) {
            $idList = array_map('trim', explode(',', $ids));
        }

        $idList = array_values(array_filter(
            array_map('strval', $idList),
            static fn (string $id): bool => $id !== '',
        ));

        if ($idList === []) {
            return json_encode(['error' => 'No valid IDs provided.'], \JSON_THROW_ON_ERROR);
        }

        $deletePayload = array_map(static fn (string $id): array => ['id' => $id], $idList);

        if ($dryRun) {
            $this->connection->beginTransaction();

            try {
                $events = $repository->delete($deletePayload, $context);

                $deleted = [];
                foreach ($events->getEvents()?->getElements() ?? [] as $event) {
                    $deleted[] = [
                        'entity' => $event->getEntityName(),
                        'ids' => $event->getIds(),
                    ];
                }

                return json_encode([
                    'dryRun' => true,
                    'success' => true,
                    'wouldDelete' => $deleted,
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

        $events = $repository->delete($deletePayload, $context);

        $deleted = [];
        foreach ($events->getEvents()?->getElements() ?? [] as $event) {
            $deleted[] = [
                'entity' => $event->getEntityName(),
                'ids' => $event->getIds(),
            ];
        }

        return json_encode([
            'dryRun' => false,
            'success' => true,
            'deleted' => $deleted,
        ], \JSON_THROW_ON_ERROR);
    }
}
