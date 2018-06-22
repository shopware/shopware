<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM;

use Shopware\Core\Framework\ORM\Field\AssociationInterface;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\ORM\Write\Flag\ReadOnly;
use Shopware\Core\Framework\Struct\Collection;

class FieldCollection extends Collection
{
    /**
     * @var Field[]
     */
    protected $mapping = [];

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

    public function filterBasic(): self
    {
        return $this->filter(
            function (Field $field) {
                if ($field instanceof AssociationInterface) {
                    return $field->loadInBasic();
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
