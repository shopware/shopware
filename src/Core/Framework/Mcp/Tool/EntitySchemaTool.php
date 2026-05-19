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

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-entity-schema', title: 'Entity Schema', description: 'Get the field and association schema of a Shopware entity definition. Use this first to discover field names, types, and associations before building search criteria with shopware-entity-search. Returns {success, data: {fields: [...], associations: [...]}}. See shopware://entities resource for all available entity names.')]
#[Package('framework')]
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
        if (!$this->registry->has($entity)) { // @codeCoverageIgnore
            return $this->error(\sprintf('Entity "%s" not found. Use the shopware://entities resource for available entity names.', $entity)); // @codeCoverageIgnore
        }

        $definition = $this->registry->getByEntityName($entity);

        $fields = [];
        $associations = [];

        foreach ($definition->getFields() as $field) {
            if ($field instanceof AssociationField) {
                $associations[] = [
                    'name' => $field->getPropertyName(),
                    'type' => match (true) {
                        $field instanceof ManyToManyAssociationField => 'many-to-many',
                        $field instanceof OneToManyAssociationField => 'one-to-many',
                        $field instanceof ManyToOneAssociationField => 'many-to-one',
                        $field instanceof OneToOneAssociationField => 'one-to-one',
                        default => 'association',
                    },
                    'entity' => $field->getReferenceDefinition()->getEntityName(),
                ];

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
