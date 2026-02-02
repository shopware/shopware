<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class AttributeTranslationDefinition extends EntityTranslationDefinition
{
    private readonly CamelCaseToSnakeCaseNameConverter $converter;

    public function __construct(private readonly EntityMetadata $meta)
    {
        $this->converter = new CamelCaseToSnakeCaseNameConverter();
    }

    public function getEntityName(): string
    {
        return $this->meta->entityName . '_translation';
    }

    protected function getParentDefinitionClass(): string
    {
        return $this->meta->entityName;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = [];

        foreach ($this->meta->fields as $fieldMeta) {
            if (!$fieldMeta->attribute->translated) {
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
