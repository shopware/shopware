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
#[McpTool(name: 'shopware-entity-delete', description: 'Delete Shopware entities by their UUIDs. Always use dryRun=true (default) first to preview cascade effects and dependent entity deletions, then set dryRun=false to execute. Returns {success, data: {deleted, notFound}, _meta: {dryRun}}.')]
#[Package('framework')]
class EntityDeleteTool
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

    public function __invoke(string $entity, string $ids, bool $dryRun = true): string
    {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, $entity . ':delete')) {
            return $error;
        }

        $idList = json_decode($ids, true);
        if (!\is_array($idList)) {
            $idList = array_map('trim', explode(',', $ids));
        }

        $idList = array_values(array_filter(
            array_map('strval', $idList),
            static fn (string $id): bool => $id !== '',
        ));

        if ($idList === []) {
            return $this->error('No valid IDs provided.');
        }

        $repository = $this->registry->getRepository($entity);
        $deletePayload = array_map(static fn (string $id): array => ['id' => $id], $idList);

        if ($dryRun) {
            return $this->executeWithDryRun($this->connection, function () use ($repository, $deletePayload, $context) {
                $events = $repository->delete($deletePayload, $context);

                return $this->success($this->formatWriteEvents($events, 'delete'), ['dryRun' => true]);
            });
        }

        $events = $repository->delete($deletePayload, $context);

        return $this->success($this->formatWriteEvents($events, 'delete'), ['dryRun' => false]);
    }
}
