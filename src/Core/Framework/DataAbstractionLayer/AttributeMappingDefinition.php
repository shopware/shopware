<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class AttributeMappingDefinition extends MappingEntityDefinition
{
    private readonly CamelCaseToSnakeCaseNameConverter $converter;

    public function __construct(private readonly MappingMetadata $meta)
    {
        parent::__construct();
        $this->converter = new CamelCaseToSnakeCaseNameConverter();
    }

    public function getEntityName(): string
    {
        return $this->meta->entityName;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = [];

        foreach ($this->meta->fields as $fieldMeta) {
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

        // check for source entity is version-aware and attach reference version field
        if ($this->registry->getByClassOrEntityName($this->meta->source)->isVersionAware()) {
            $fields[] = (new ReferenceVersionField($this->meta->source))->addFlags(new PrimaryKey(), new Required());
        }

        // check for reference entity is version-aware and attach reference version field
        if ($this->registry->getByClassOrEntityName($this->meta->reference)->isVersionAware()) {
            $fields[] = (new ReferenceVersionField($this->meta->reference))->addFlags(new PrimaryKey(), new Required());
        }

        return new FieldCollection($fields);
    }
}
