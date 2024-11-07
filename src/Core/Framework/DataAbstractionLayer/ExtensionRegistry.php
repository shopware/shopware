<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 * Contains all registered entity extensions in the system
 */
#[Package('core')]
class ExtensionRegistry
{
    /**
     * @internal
     *
     * @param iterable<EntityExtension> $extensions
     * @param iterable<BulkEntityExtension> $bulks
     */
    public function __construct(
        private readonly iterable $extensions,
        private readonly iterable $bulks
    ) {
    }

    /**
     * @return iterable<EntityExtension>
     */
    public function getExtensions(): iterable
    {
        return $this->extensions;
    }

    /**
     * @return array<EntityExtension>
     */
    public function buildBulkExtensions(DefinitionInstanceRegistry $registry): array
    {
        $extensions = [];

        foreach ($this->bulks as $bulk) {
            foreach ($bulk->collect() as $entity => $fields) {
                try {
                    // @deprecated tag:v6.7.0 - can be removed, as it is not used anymore. The entity name is the only requirement for EntityExtensions within v6.7.0
                    $definition = $registry->getByEntityName($entity);
                } catch (DefinitionNotFoundException) {
                    continue;
                }

                $extensions[] = new class($fields, $definition->getClass(), $entity) extends EntityExtension {
                    /**
                     * @param list<Field> $fields
                     */
                    public function __construct(private readonly array $fields, private readonly string $class, private readonly string $entity)
                    {
                    }

                    public function extendFields(FieldCollection $collection): void
                    {
                        foreach ($this->fields as $field) {
                            $collection->add($field);
                        }
                    }

                    public function getDefinitionClass(): string
                    {
                        return $this->class;
                    }

                    public function getEntityName(): string
                    {
                        return $this->entity;
                    }
                };
            }
        }

        return $extensions;
    }
}
