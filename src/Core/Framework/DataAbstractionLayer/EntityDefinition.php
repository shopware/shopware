<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\Struct\ArrayEntity;

abstract class EntityDefinition
{
    /**
     * @var CompiledFieldCollection|null
     */
    protected $fields;

    /**
     * @var EntityExtension[]
     */
    protected $extensions = [];

    /**
     * @var TranslationsAssociationField|null
     */
    protected $translationField;

    /**
     * @var CompiledFieldCollection|null
     */
    protected $primaryKeys;

    /**
     * @var DefinitionInstanceRegistry
     */
    protected $registry;

    /**
     * @var TranslatedField[]
     */
    protected array $translatedFields = [];

    /**
     * @var Field[]
     */
    protected array $extensionFields = [];

    /**
     * @var EntityDefinition|false|null
     */
    private $parentDefinition = false;

    private string $className;

    final public function __construct()
    {
        $this->className = static::class;
    }

    final public function getClass(): string
    {
        return $this->className;
    }

    final public function isInstanceOf(EntityDefinition $other): bool
    {
        // same reference or instance of the other class
        return $this === $other
            || ($other->getClass() !== EntityDefinition::class && $this instanceof $other);
    }

    public function compile(DefinitionInstanceRegistry $registry): void
    {
        $this->registry = $registry;
    }

    final public function addExtension(EntityExtension $extension): void
    {
        $this->extensions[] = $extension;
        $this->fields = null;
    }

    /**
     * @internal
     * Use this only for test purposes
     */
    final public function removeExtension(EntityExtension $toDelete): void
    {
        foreach ($this->extensions as $key => $extension) {
            if (\get_class($extension) === \get_class($toDelete)) {
                unset($this->extensions[$key]);
                $this->fields = null;

                return;
            }
        }
    }

    abstract public function getEntityName(): string;

    final public function getFields(): CompiledFieldCollection
    {
        if ($this->fields !== null) {
            return $this->fields;
        }

        $fields = $this->defineFields();

        foreach ($this->defaultFields() as $field) {
            $fields->add($field);
        }

        foreach ($this->extensions as $extension) {
            $new = new FieldCollection();

            $extension->extendFields($new);

            foreach ($new as $field) {
                $field->addFlags(new Extension());

                if ($field instanceof AssociationField) {
                    $fields->add($field);

                    continue;
                }

                if ($field->is(Runtime::class)) {
                    $fields->add($field);

                    continue;
                }

                if ($field instanceof ReferenceVersionField) {
                    $fields->add($field);

                    continue;
                }

                if (!$field instanceof FkField) {
                    throw new \Exception('Only AssociationFields, FkFields/ReferenceVersionFields for a ManyToOneAssociationField or fields flagged as Runtime can be added as Extension.');
                }

                if (!$this->hasAssociationWithStorageName($field->getStorageName(), $new)) {
                    throw new \Exception(sprintf('FkField %s has no configured OneToOneAssociationField or ManyToOneAssociationField in entity %s', $field->getPropertyName(), $this->className));
                }

                $fields->add($field);
            }
        }

        foreach ($this->getBaseFields() as $baseField) {
            $fields->add($baseField);
        }

        foreach ($fields as $field) {
            if ($field instanceof TranslationsAssociationField) {
                $this->translationField = $field;
                $fields->add(
                    (new JsonField('translated', 'translated'))->addFlags(new ApiAware(), new Computed(), new Runtime())
                );

                break;
            }
        }

        $this->fields = $fields->compile($this->registry);

        return $this->fields;
    }

    final public function getProtections(): EntityProtectionCollection
    {
        $protections = $this->defineProtections();

        foreach ($this->extensions as $extension) {
            if (!$extension instanceof EntityExtension) {
                continue;
            }

            $extension->extendProtections($protections);
        }

        return $protections;
    }

    final public function getField(string $propertyName): ?Field
    {
        return $this->getFields()->get($propertyName);
    }

    /**
     * @return class-string<EntityCollection>
     */
    public function getCollectionClass(): string
    {
        return EntityCollection::class;
    }

    /**
     * @return class-string<Entity>
     */
    public function getEntityClass(): string
    {
        return ArrayEntity::class;
    }

    public function getParentDefinition(): ?EntityDefinition
    {
        if ($this->parentDefinition !== false) {
            return $this->parentDefinition;
        }

        $parentDefinitionClass = $this->getParentDefinitionClass();

        if ($parentDefinitionClass === null) {
            return $this->parentDefinition = null;
        }

        return $this->parentDefinition = $this->registry->get($parentDefinitionClass);
    }

    final public function getTranslationDefinition(): ?EntityDefinition
    {
        // value is initialized from this method
        $this->getFields();

        if ($this->translationField === null) {
            return null;
        }

        return $this->translationField->getReferenceDefinition();
    }

    final public function getTranslationField(): ?TranslationsAssociationField
    {
        // value is initialized from this method
        $this->getFields();

        return $this->translationField;
    }

    final public function getPrimaryKeys(): CompiledFieldCollection
    {
        if ($this->primaryKeys !== null) {
            return $this->primaryKeys;
        }

        $fields = $this->getFields()->filter(function (Field $field): bool {
            return $field->is(PrimaryKey::class);
        });

        $fields->sort(static function (Field $a, Field $b) {
            return $b->getExtractPriority() <=> $a->getExtractPriority();
        });

        return $this->primaryKeys = $fields;
    }

    public function getDefaults(): array
    {
        return [];
    }

    public function getChildDefaults(): array
    {
        return [];
    }

    public function isChildrenAware(): bool
    {
        //used in VersionManager
        return $this->getFields()->getChildrenAssociationField() !== null;
    }

    public function isParentAware(): bool
    {
        return $this->getFields()->get('parent') instanceof ParentAssociationField;
    }

    public function isInheritanceAware(): bool
    {
        return false;
    }

    public function isVersionAware(): bool
    {
        return $this->getFields()->has('versionId');
    }

    public function isLockAware(): bool
    {
        $field = $this->getFields()->get('locked');

        return $field && $field instanceof LockedField;
    }

    public function isSeoAware(): bool
    {
        $field = $this->getFields()->get('seoUrls');

        return $field instanceof OneToManyAssociationField && $field->getReferenceClass() === SeoUrlDefinition::class;
    }

    public function since(): ?string
    {
        return null;
    }

    public function getHydratorClass(): string
    {
        return EntityHydrator::class;
    }

    /**
     * @internal
     */
    public function decode(string $property, ?string $value)
    {
        $field = $this->getField($property);

        if ($field === null) {
            throw new \RuntimeException(sprintf('Field %s not found', $property));
        }

        return $field->getSerializer()->decode($field, $value);
    }

    public function getTranslatedFields(): array
    {
        return $this->getFields()->getTranslatedFields();
    }

    public function getExtensionFields(): array
    {
        return $this->getFields()->getExtensionFields();
    }

    protected function getParentDefinitionClass(): ?string
    {
        return null;
    }

    /**
     * @return Field[]
     */
    protected function defaultFields(): array
    {
        return [
            (new CreatedAtField())->addFlags(new ApiAware()),
            (new UpdatedAtField())->addFlags(new ApiAware()),
        ];
    }

    abstract protected function defineFields(): FieldCollection;

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection();
    }

    protected function getBaseFields(): array
    {
        return [];
    }

    private function hasAssociationWithStorageName(string $storageName, FieldCollection $new): bool
    {
        foreach ($new as $association) {
            if (!$association instanceof ManyToOneAssociationField && !$association instanceof OneToOneAssociationField) {
                continue;
            }

            if ($association->getStorageName() === $storageName) {
                return true;
            }
        }

        return false;
    }
}
