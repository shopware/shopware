<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal Used for custom entities
 */
class DynamicEntityDefinition extends EntityDefinition
{
    protected string $name;

    protected array $fieldDefinitions;

    public static function create(string $name, array $fields): DynamicEntityDefinition
    {
        $self = new self();
        $self->name = $name;
        $self->fieldDefinitions = $fields;

        return $self;
    }

    public function getEntityName(): string
    {
        return $this->name;
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
        ]);

        foreach ($this->fieldDefinitions as $definition) {
            switch ($definition['type']) {
                default:
                    $field = new StringField($definition['name'], $definition['name']);

                    break;
            }

            $collection->add($field);
        }

        return $collection;
    }
}
