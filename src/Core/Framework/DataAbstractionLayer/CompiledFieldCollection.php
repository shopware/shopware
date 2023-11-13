<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class CompiledFieldCollection extends FieldCollection
{
    /**
     * @var array<string, Field>
     */
    protected array $mappedByStorageName = [];

    private ?ChildrenAssociationField $childrenAssociationField = null;

    /**
     * @var array<string, TranslatedField>
     */
    private array $translatedFields = [];

    /**
     * @var array<string, Field>
     */
    private array $extensionFields = [];

    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        iterable $elements = []
    ) {
        foreach ($elements as $element) {
            $this->addField($element);
        }
    }

    /**
     * @param Field $field
     */
    public function add($field): void
    {
        if (!$field->isCompiled()) {
            throw new \BadMethodCallException('This action is not recommended if you still need to compile the field');
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

    /**
     * @return array<string, TranslatedField>
     */
    public function getTranslatedFields(): array
    {
        return $this->translatedFields;
    }

    /**
     * @return array<string, Field>
     */
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

    /**
     * @deprecated tag:v6.6.0 - Will be removed without replacement as it is unused
     *
     * @return list<string>
     */
    public function getMappedByStorageName()
    {
        Feature::triggerDeprecationOrThrow('v6_6_0_0', Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0'));

        return array_keys($this->mappedByStorageName);
    }

    public function getByStorageName(string $storageName): ?Field
    {
        return $this->mappedByStorageName[$storageName] ?? null;
    }

    /**
     * @param class-string<Flag> $flagClass
     */
    public function filterByFlag(string $flagClass): self
    {
        return $this->filter(static fn (Field $field) => $field->is($flagClass));
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
