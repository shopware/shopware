<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field as FieldAttribute;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
final readonly class FieldMetadata
{
    /**
     * @param class-string<Field> $fieldClass
     * @param FieldAttribute $attribute Source attribute (ManyToOne, OneToMany, Field, etc.)
     * @param list<FlagMetadata> $flags
     * @param string|null $propertyType PHP type class (e.g., enum) for type-specific field creation
     *
     * @throw DataAbstractionLayerException
     */
    public function __construct(
        public string $fieldClass,
        public string $propertyName,
        public FieldAttribute $attribute,
        public string $entityName,
        public array $flags = [],
        public ?string $propertyType = null,
    ) {
        if (!is_a($fieldClass, Field::class, true)) {
            throw DataAbstractionLayerException::invalidFieldMetadataClass($fieldClass);
        }
    }

    public function toDefinition(): Definition
    {
        return new Definition(self::class, [
            $this->fieldClass,
            $this->propertyName,
            $this->attribute->toDefinition(),
            $this->entityName,
            array_map(fn (FlagMetadata $f) => $f->toDefinition(), $this->flags),
            $this->propertyType,
        ]);
    }
}
