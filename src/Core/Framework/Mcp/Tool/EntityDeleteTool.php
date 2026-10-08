<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpTool(
    name: 'shopware-entity-delete',
    title: 'Entity Delete',
    description: 'Delete Shopware entities by their UUIDs. Also the way to REMOVE a many-to-many link without deleting either side: delete the mapping entity, e.g. entity "product_category" with ids [{"productId":"...","categoryId":"..."}] removes a category from a product, "product_property" with [{"productId":"...","optionId":"..."}] removes a property option. shopware-entity-upsert can only add such links. Always use dryRun=true (default) first to preview cascade effects, then set dryRun=false to execute. Returns each affected entity with its operation: delete, or update for the entities a removed link belonged to.'
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
            return $this->error(\sprintf('Entity "%s" not found. Use the shopware://entities resource for available entity names.', $entity), McpToolError::INVALID_ARGUMENTS);
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

                return $this->success($this->formatDeleteEvents($events), ['dryRun' => true]);
            });
        }

        $events = $repository->delete($deletePayload, $context);

        return $this->success($this->formatDeleteEvents($events), ['dryRun' => false]);
    }

    /**
     * Like formatWriteEvents(), but with each event's own operation. A delete also reports the entities
     * whose associations it changed: removing a `product_category` row emits written events for the
     * product and the category next to the deleted mapping row. Labelling those "delete" tells a client,
     * and a model reading a dry run, that the product and the category would be deleted.
     *
     * @return list<array{entity: string, ids: list<string|array<string, string>>, operation: string}>
     */
    private function formatDeleteEvents(EntityWrittenContainerEvent $events): array
    {
        $result = [];
        foreach ($events->getEvents()?->getElements() ?? [] as $event) {
            $result[] = [
                'entity' => $event->getEntityName(),
                'ids' => $event->getIds(),
                'operation' => $event instanceof EntityDeletedEvent ? 'delete' : 'update',
            ];
        }

        return $result;
    }

    /**
     * Builds the DAL delete payload. Entities with a single primary key take plain UUIDs.
     * Mapping entities (for example `product_category`) have a composite primary key, so each
     * row is named by an object holding every key field. Version fields the caller names are kept.
     * Otherwise they default to the live version, which is only unambiguous in a live context:
     * `DELETE /api/product/{id}/categories/{categoryId}` uses the context version for the parent and
     * the live version for the referenced side, and a mapping row does not say which side is which.
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
                $version = $row[$versionField] ?? null;
                if ($version !== null) {
                    if (!\is_string($version) || !Uuid::isValid($version)) {
                        return $this->error(\sprintf('"%s" must be a version UUID.', $versionField));
                    }

                    $keys[$versionField] = $version;

                    continue;
                }

                // Each side of a link can be in a different version: the Admin API unlinks with the
                // context version for the parent and the live version for the referenced entity. A
                // row has no parent, so outside the live version the caller has to say which, or the
                // key matches no row and nothing is removed.
                if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
                    return $this->error(\sprintf(
                        'This request runs in version %s. Name every version field (%s) in each ids object, for example the live version %s for a side that is not being edited.',
                        $context->getVersionId(),
                        implode(', ', $versionFields),
                        Defaults::LIVE_VERSION,
                    ));
                }

                $keys[$versionField] = Defaults::LIVE_VERSION;
            }

            $payload[] = $keys;
        }

        return $payload;
    }
}
