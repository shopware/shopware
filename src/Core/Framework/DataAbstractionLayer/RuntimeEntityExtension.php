<?php

namespace Shopware\Core\Framework\DataAbstractionLayer;

/**
 * @internal will be generated when BulkEntityExtension are registered
 */
class RuntimeEntityExtension extends EntityExtension
{
    public function __construct(private readonly array $fields, private readonly string $class)
    {}

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
}
