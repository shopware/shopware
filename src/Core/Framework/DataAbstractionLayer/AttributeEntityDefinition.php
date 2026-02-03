<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class AttributeEntityDefinition extends EntityDefinition implements AttributeBasedEntityDefinition
{
    private readonly CamelCaseToSnakeCaseNameConverter $converter;

    public function __construct(private readonly EntityMetadata $meta)
    {
        $this->converter = new CamelCaseToSnakeCaseNameConverter();
    }

    public function since(): ?string
    {
        return $this->meta->since;
    }

    /**
     * @return class-string<Entity>
     */
    public function getEntityClass(): string
    {
        return $this->meta->entityClass;
    }

    public function getEntityName(): string
    {
        return $this->meta->entityName;
    }

    /**
     * @return class-string<EntityCollection<Entity>>
     */
    public function getCollectionClass(): string
    {
        return $this->meta->collectionClass;
    }

    /**
     * @return class-string<EntityHydrator>
     */
    public function getHydratorClass(): string
    {
        return $this->meta->hydratorClass;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return $this->meta->parent;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = [];

        foreach ($this->meta->fields as $fieldMeta) {
            if ($fieldMeta->attribute->translated) {
                $fields[] = new TranslatedField($fieldMeta->propertyName);
                continue;
            }

            $column = $this->converter->normalize($fieldMeta->propertyName);

            $field = $fieldMeta->attribute->createField(
                $fieldMeta->propertyName,
                $column,
                $fieldMeta->entityName,
                $fieldMeta->propertyType,
            );

            foreach ($fieldMeta->flags as $flagMeta) {
                $field->addFlags($flagMeta->createFlag());
            }

            $fields[] = $field;
        }

        return new FieldCollection($fields);
    }
}
