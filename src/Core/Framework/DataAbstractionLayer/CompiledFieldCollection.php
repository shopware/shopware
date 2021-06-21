<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;

/**
 * @method void          set(string $key, Field $entity)
 * @method array<string, Field> getIterator()
 * @method Field|null    first()
 * @method Field|null    last()
 */
class CompiledFieldCollection extends FieldCollection
{
    /**
     * @var Field[]
     */
    protected $mappedByStorageName = [];

    /**
     * @var ChildrenAssociationField|null
     */
    private $childrenAssociationField;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var TranslatedField[]
     */
    private array $translatedFields = [];

    /**
     * @var Field[]
     */
    private array $extensionFields = [];

    public function __construct(DefinitionInstanceRegistry $registry, iterable $elements = [])
    {
        foreach ($elements as $element) {
            $this->addField($element);
        }

        $this->registry = $registry;
    }

    /**
     * @param Field $field
     */
    public function add($field): void
    {
        if (!$field->isCompiled()) {
            throw new \BadMethodCallException('This action is not recommended nif you still need to ');
        }
        $this->addField($field);
    }

    public function addNewField(Field $field): void
    {
        $field->compile($this->registry);
        $this->addField($field);
    }

    public function addField(Field $field): void
    {
        $this->elements[$field->getPropertyName()] = $field;

        if ($field instanceof StorageAware && !$field->getFlag(Runtime::class)) {
            $this->mappedByStorageName[$field->getStorageName()] = $field;
        }

        if ($field instanceof ChildrenAssociationField) {
            $this->childrenAssociationField = $field;
        }

        if ($field instanceof TranslatedField) {
            $this->translatedFields[$field->getPropertyName()] = $field;
        }

        if ($field->is(Extension::class)) {
            $this->extensionFields[$field->getPropertyName()] = $field;
        }
    }

    public function getTranslatedFields(): array
    {
        return $this->translatedFields;
    }

    public function getExtensionFields(): array
    {
        return $this->extensionFields;
    }

    /**
     * @param string $fieldName
     *
     * @internal
     */
    public function remove($fieldName): void
    {
        if (isset($this->mappedByStorageName[$fieldName])) {
            unset($this->mappedByStorageName[$fieldName]);
        }

        parent::remove($fieldName);
    }

    public function get($propertyName): ?Field
    {
        return $this->elements[$propertyName] ?? null;
    }

    public function getBasicFields(): self
    {
        return $this->filter(
            function (Field $field) {
                if ($field instanceof AssociationField) {
                    return $field->getAutoload();
                }

                return true;
            }
        );
    }

    public function getMappedByStorageName()
    {
        return array_keys($this->mappedByStorageName);
    }

    public function getByStorageName(string $storageName): ?Field
    {
        return $this->mappedByStorageName[$storageName] ?? null;
    }

    public function filterByFlag(string $flagClass): self
    {
        $ret = $this->filter(static function (Field $field) use ($flagClass) {
            return $field->is($flagClass);
        });

        return $ret;
    }

    public function getChildrenAssociationField(): ?ChildrenAssociationField
    {
        return $this->childrenAssociationField;
    }

    public function getApiAlias(): string
    {
        return 'dal_compiled_field_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return Field::class;
    }

    protected function createNew(iterable $elements = []): CompiledFieldCollection
    {
        return new self($this->registry, $elements);
    }
}
