<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
final readonly class EntityMetadata
{
    /**
     * @param non-empty-string $entityName
     * @param class-string<Entity> $entityClass
     * @param class-string<EntityCollection<Entity>> $collectionClass
     * @param class-string<EntityHydrator> $hydratorClass
     * @param list<FieldMetadata> $fields
     * @param string|null $parent Parent definition class for inherited entities
     */
    public function __construct(
        public string $entityName,
        public string $entityClass,
        public string $collectionClass,
        public string $hydratorClass,
        public array $fields,
        public ?string $since = null,
        public ?string $parent = null,
    ) {
    }

    public function hasTranslation(): bool
    {
        foreach ($this->fields as $field) {
            if ($field->attribute->translated) {
                return true;
            }
        }

        return false;
    }

    public function toDefinition(): Definition
    {
        return new Definition(self::class, [
            $this->entityName,
            $this->entityClass,
            $this->collectionClass,
            $this->hydratorClass,
            array_map(fn (FieldMetadata $f) => $f->toDefinition(), $this->fields),
            $this->since,
            $this->parent,
        ]);
    }
}
