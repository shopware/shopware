<?php

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

class AttributeTranslationDefinition extends EntityTranslationDefinition
{
    public function __construct(private readonly array $meta = [])
    {
        parent::__construct();
    }

    public function getEntityName(): string
    {
        return $this->meta['entity_name'] . '_translation';
    }

    protected function getParentDefinitionClass(): string
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
            if (!$field['translated']) {
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
