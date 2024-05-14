<?php

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

class AttributeMappingDefinition extends MappingEntityDefinition
{
    public function __construct(private readonly array $meta = [])
    {
        parent::__construct();
    }

    public function getEntityName(): string
    {
        return $this->meta['entity_name'];
    }

    protected function defineFields(): FieldCollection
    {
        $fields = [];
        foreach ($this->meta['fields'] as $field) {
            if (!isset($field['class'])) {
                continue;
            }

            /** @var Field $instance */
            $instance = new $field['class'](...$field['args']);

            foreach ($field['flags'] ?? [] as $flag) {
                $instance->addFlags(new $flag['class'](...$flag['args'] ?? []));
            }

            $fields[] = $instance;
        }

        return new FieldCollection($fields);
    }
}
