<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;

class AttributeEntityDefinition extends EntityDefinition
{
    public function __construct(private readonly array $meta = [])
    {
        parent::__construct();
    }

    public function getEntityClass(): string
    {
        return $this->meta['entity_class'];
    }

    public function getEntityName(): string
    {
        return $this->meta['entity_name'];
    }

    protected function getParentDefinitionClass(): ?string
    {
        return $this->meta['parent'] ?? null;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = [];
        foreach ($this->meta['fields'] as $field) {
            if (!isset($field['class'])) {
                continue;
            }

            if ($field['translated']) {
                $fields[] = new TranslatedField($field['name']);
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
