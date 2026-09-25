<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpTool(
    name: 'shopware-entity-schema',
    title: 'Entity Schema',
    description: 'Get the field and association schema of a Shopware entity definition: field names, types, and associations for building shopware-entity-search criteria. Many-to-many associations also name their mappingEntity (e.g. product categories: entity "category", mappingEntity "product_category"); delete a mapping row with shopware-entity-delete to remove a link. Returns {success, data: {fields: [...], associations: [...]}}. See shopware://entities resource for all available entity names.'
)]
#[McpToolGroup('entity')]
class EntitySchemaTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
    ) {
    }

    public function __invoke(string $entity): string
    {
        if (!$this->registry->has($entity)) {
            return $this->error(\sprintf('Entity "%s" not found. Use the shopware://entities resource for available entity names.', $entity));
        }

        $definition = $this->registry->getByEntityName($entity);

        $fields = [];
        $associations = [];

        foreach ($definition->getFields() as $field) {
            if ($field instanceof AssociationField) {
                $association = [
                    'name' => $field->getPropertyName(),
                    'type' => match (true) {
                        $field instanceof ManyToManyAssociationField => 'many-to-many',
                        $field instanceof OneToManyAssociationField => 'one-to-many',
                        $field instanceof ManyToOneAssociationField => 'many-to-one',
                        $field instanceof OneToOneAssociationField => 'one-to-one',
                        default => 'association',
                    },
                    // For many-to-many the reference definition is the mapping entity, so name the
                    // target entity here and the mapping entity separately. Links are added through
                    // upsert and removed by deleting the mapping row, like the Admin API sync endpoint.
                    'entity' => $field instanceof ManyToManyAssociationField
                        ? $field->getToManyReferenceDefinition()->getEntityName()
                        : $field->getReferenceDefinition()->getEntityName(),
                ];

                if ($field instanceof ManyToManyAssociationField) {
                    $association['mappingEntity'] = $field->getMappingDefinition()->getEntityName();
                }

                $associations[] = $association;

                continue;
            }

            $fields[] = [
                'name' => $field->getPropertyName(),
                'type' => match (true) {
                    $field instanceof IdField => 'uuid',
                    $field instanceof FkField => 'fk',
                    $field instanceof BoolField => 'bool',
                    $field instanceof IntField => 'int',
                    $field instanceof FloatField => 'float',
                    $field instanceof DateTimeField => 'datetime',
                    $field instanceof JsonField => 'json',
                    default => 'string',
                },
                'required' => $field->is(Required::class),
            ];
        }

        return $this->success([
            'entity' => $entity,
            'fields' => $fields,
            'associations' => $associations,
        ]);
    }
}
