<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
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
    name: 'shopware-entity-delete',
    title: 'Entity Delete',
    description: 'Delete Shopware entities by their UUIDs. Also the way to REMOVE a many-to-many link without deleting either side: delete the mapping entity, e.g. entity "product_category" with ids [{"productId":"...","categoryId":"..."}] removes a category from a product, "product_property" with [{"productId":"...","optionId":"..."}] removes a property option. shopware-entity-upsert can only add such links. Always use dryRun=true (default) first to preview cascade effects and dependent entity deletions, then set dryRun=false to execute. Returns {success, data: {deleted, notFound}, _meta: {dryRun}}.'
)]
#[McpToolDependsOn('shopware-entity-search')]
#[McpToolGroup('entity')]
#[McpToolRequires(entityParam: 'entity', operations: ['delete'])]
class EntityDeleteTool extends McpToolResponse
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
        #[Schema(description: 'Entity name to delete from, e.g. "product", or a mapping entity such as "product_category" to remove a many-to-many link. See the shopware://entities resource for the full list.')]
        string $entity,
        #[Schema(description: 'What to delete. For normal entities: a JSON array of UUIDs (["..."]) or a comma-separated list. For mapping entities with a composite primary key: a JSON array of objects naming every key field, e.g. [{"productId":"...","categoryId":"..."}]. shopware-entity-schema lists the key fields.')]
        string $ids,
        #[Schema(description: 'Preview without deleting. Leave true first, then call again with false to delete.')]
        bool $dryRun = true,
    ): string {
        $context = $this->contextProvider->getContext();

        if (!$this->registry->has($entity)) {
            return $this->error(\sprintf('Entity "%s" not found. Use the shopware://entities resource for available entity names.', $entity));
        }

        if ($error = $this->requirePrivilege($context, $entity . ':delete')) {
            return $error;
        }

        $deletePayload = $this->buildDeletePayload($this->registry->getByEntityName($entity), $ids, $context);
        if (\is_string($deletePayload)) {
            return $deletePayload;
        }

        $repository = $this->registry->getRepository($entity);

        if ($dryRun) {
            return $this->executeWithDryRun($this->connection, $context, function () use ($repository, $deletePayload, $context) {
                $events = $repository->delete($deletePayload, $context);

                return $this->success($this->formatWriteEvents($events, 'delete'), ['dryRun' => true]);
            });
        }

        $events = $repository->delete($deletePayload, $context);

        return $this->success($this->formatWriteEvents($events, 'delete'), ['dryRun' => false]);
    }

    /**
     * Builds the DAL delete payload. Entities with a single primary key take plain UUIDs.
     * Mapping entities (for example `product_category`) have a composite primary key, so each
     * row is named by an object holding every key field. Version fields are filled from the
     * context, like the Admin API does for `DELETE /api/product/{id}/categories/{categoryId}`.
     *
     * @return list<array<string, string>>|string the payload, or an error response
     */
    private function buildDeletePayload(EntityDefinition $definition, string $ids, Context $context): array|string
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

        $decoded = json_decode($ids, true);

        if (\count($keyFields) <= 1) {
            $idList = \is_array($decoded) ? $decoded : array_map('trim', explode(',', $ids));
            $idList = array_values(array_filter(
                $idList,
                static fn (mixed $id): bool => \is_string($id) && $id !== '',
            ));

            if ($idList === []) {
                return $this->error('No valid IDs provided.');
            }

            return array_map(static fn (string $id): array => ['id' => $id], $idList);
        }

        $example = json_encode([array_fill_keys($keyFields, '...')], \JSON_THROW_ON_ERROR);
        $usage = \sprintf('Entity "%s" has a composite primary key. Pass ids as a JSON array of objects with %s, e.g. %s.', $definition->getEntityName(), implode(' and ', $keyFields), $example);

        if (!\is_array($decoded) || $decoded === [] || !array_is_list($decoded)) {
            return $this->error($usage);
        }

        $payload = [];
        foreach ($decoded as $row) {
            if (!\is_array($row)) {
                return $this->error($usage);
            }

            $keys = [];
            foreach ($keyFields as $keyField) {
                $value = $row[$keyField] ?? null;
                if (!\is_string($value) || $value === '') {
                    return $this->error($usage);
                }

                $keys[$keyField] = $value;
            }

            foreach ($versionFields as $versionField) {
                $keys[$versionField] = $context->getVersionId();
            }

            $payload[] = $keys;
        }

        return $payload;
    }
}
