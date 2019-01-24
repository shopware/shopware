<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;

class JsonStorefrontApiEncoder extends JsonApiEncoder
{
    /**
     * @var string[][]
     */
    private $allowedRelationships = [];

    /**
     * @param Entity|EntityCollection|null $value
     */
    protected function addRelationships(
        SerializedEntity $serialized,
        SerializedCollection $entities,
        string $baseUrl,
        string $self,
        Field $field,
        $value,
        string $propertyName
    ): void {
        $type = $serialized->getType();
        $endpointName = $this->camelCaseToSnailCase($type);

        $relationshipAllowed = isset($this->allowedRelationships[$endpointName][$propertyName]);
        if ($field instanceof ManyToOneAssociationField) {
            $foreignKey = null;

            if ($value instanceof Entity) {
                $nested = $this->serializeEntity($entities, $field->getReferenceClass(), $value, $baseUrl);

                $entities->addIncluded($nested);

                $foreignKey = [
                    'id' => $nested->getId(),
                    'type' => $field->getReferenceClass()::getEntityName(),
                ];
            }

            if ($relationshipAllowed) {
                $serialized->addRelationship(
                    $propertyName,
                    [
                        'data' => $foreignKey,
                        'links' => [
                            'related' => $self . '/' . $this->camelCaseToSnailCase($propertyName),
                        ],
                    ]
                );
            }

            return;
        }

        if ($field instanceof OneToManyAssociationField) {
            $foreignKey = [];

            if ($value instanceof EntityCollection) {
                $reference = $field->getReferenceClass();
                foreach ($value as $nestedEntity) {
                    $nested = $this->serializeEntity($entities, $reference, $nestedEntity, $baseUrl);

                    $entities->addIncluded($nested);

                    $foreignKey[] = [
                        'id' => $nested->getId(),
                        'type' => $reference::getEntityName(),
                    ];
                }
            }

            if ($relationshipAllowed) {
                $serialized->addRelationship(
                    $propertyName,
                    [
                        'data' => $foreignKey,
                        'links' => [
                            'related' => $self . '/' . $this->camelCaseToSnailCase($propertyName),
                        ],
                    ]
                );
            }

            return;
        }

        if ($field instanceof  ManyToManyAssociationField) {
            $foreignKey = [];

            if ($value instanceof EntityCollection) {
                $reference = $field->getReferenceDefinition();
                foreach ($value as $nestedEntity) {
                    $nested = $this->serializeEntity($entities, $reference, $nestedEntity, $baseUrl);

                    $entities->addIncluded($nested);

                    $foreignKey[] = [
                        'id' => $nestedEntity->getId(),
                        'type' => $reference::getEntityName(),
                    ];
                }
            }

            if ($relationshipAllowed) {
                $serialized->addRelationship(
                    $propertyName,
                    [
                        'data' => $foreignKey,
                        'links' => [
                            'related' => $self . '/' . $this->camelCaseToSnailCase($propertyName),
                        ],
                    ]
                );
            }
        }
    }
}
