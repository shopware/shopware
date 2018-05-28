<?php declare(strict_types=1);

namespace Shopware\Framework\ORM;

use Shopware\Framework\ORM\Field\AssociationInterface;
use Shopware\Framework\ORM\Field\Field;
use Shopware\Framework\ORM\Write\FieldAware\StorageAware;
use Shopware\Framework\ORM\Write\Flag\ReadOnly;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Framework\Struct\Collection;

class FieldCollection extends Collection
{
    /**
     * @var Field[]
     */
    public $mapping = [];
    /**
     * @var Field[]
     */
    protected $elements = [];

    public function __construct(array $elements = [])
    {
        foreach ($elements as $field) {
            $this->add($field);
        }
    }

    public function add(Field $field)
    {
        $this->elements[$field->getPropertyName()] = $field;
        if ($field instanceof StorageAware && !$field instanceof AssociationInterface) {
            $this->mapping[$field->getStorageName()] = $field;
        }
    }

    public function get(string $propertyName): ?Field
    {
        return $this->elements[$propertyName] ?? null;
    }

    public function getBasicProperties(): self
    {
        return $this->filter(
            function (Field $field) {
                if ($field instanceof AssociationInterface) {
                    return $field->loadInBasic() && !$field->is(WriteOnly::class);
                }

                return true;
            }
        );
    }

    public function getDetailProperties(): self
    {
        return $this->filter(
            function (Field $field) {
                if ($field instanceof AssociationInterface) {
                    return !$field->is(WriteOnly::class);
                }

                return true;
            }
        );
    }

    public function getWritableFields(): self
    {
        return $this->filter(function (Field $field) {
            return !$field->is(ReadOnly::class);
        });
    }

    public function current(): Field
    {
        return parent::current();
    }

    public function getByStorageName(string $storageName): ?Field
    {
        return $this->mapping[$storageName] ?? null;
    }

    public function filterByFlag(string $flagClass): self
    {
        return $this->filter(function (Field $field) use ($flagClass) {
            return $field->is($flagClass);
        });
    }
}
