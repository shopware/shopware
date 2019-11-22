<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\Struct\ArrayEntity;

abstract class EntityDefinition
{
    /**
     * @var CompiledFieldCollection|null
     */
    protected $fields;

    /**
     * @var EntityExtensionInterface[]
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
     * @var EntityDefinition|false|null
     */
    private $parentDefinition = false;

    /**
     * @var string
     */
    private $className;

    final public function __construct()
    {
        $this->className = get_class($this);
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

    final public function addExtension(EntityExtensionInterface $extension): void
    {
        $this->extensions[] = $extension;
        $this->fields = null;
    }

    /**
     * @internal
     * Use this only for test purposes
     */
    final public function removeExtension(EntityExtensionInterface $toDelete): void
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

            /** @var Field $field */
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

                if (!$field instanceof FkField) {
                    throw new \Exception('Only AssociationFields, fk fields for a ManyToOneAssociationField or fields flagged as Runtime can be added as Extension.');
                }

                $hasManyToOne = false;
                foreach ($new as $association) {
                    if (!$association instanceof ManyToOneAssociationField) {
                        continue;
                    }

                    if ($association->getStorageName() === $field->getStorageName()) {
                        $hasManyToOne = true;
                        break;
                    }
                }

                if (!$hasManyToOne) {
                    throw new \Exception(sprintf('FkField %s has no configured ManyToOneAssociationField in entity %s', $field->getPropertyName(), $this->className));
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
                    (new JsonField('translated', 'translated'))->addFlags(new Computed(), new Runtime())
                );
                break;
            }
        }

        $this->fields = $fields->compile($this->registry);

        return $this->fields;
    }

    final public function getField(string $propertyName): ?Field
    {
        return $this->getFields()->get($propertyName);
    }

    public function getCollectionClass(): string
    {
        return EntityCollection::class;
    }

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

    final public function getPrimaryKeys(): CompiledFieldCollection
    {
        if ($this->primaryKeys !== null) {
            return $this->primaryKeys;
        }

        $fields = $this->getFields()->filter(function (Field $field): bool {
            return $field->is(PrimaryKey::class);
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

    public function isChildCountAware(): bool
    {
        return $this->getFields()->get('childCount') instanceof ChildCountField;
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

    public function isBlacklistAware(): bool
    {
        return $this->getFields()->has('blacklistIds');
    }

    public function isWhitelistAware(): bool
    {
        return $this->getFields()->has('whitelistIds');
    }

    public function isTreeAware(): bool
    {
        return $this->isParentAware()
            && ($this->getFields()->filterInstance(TreePathField::class)->count() > 0
                || $this->getFields()->filterInstance(TreeLevelField::class)->count() > 0);
    }

    public function isLockAware(): bool
    {
        $field = $this->getFields()->get('locked');

        return $field && $field instanceof LockedField;
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
            new CreatedAtField(),
            new UpdatedAtField(),
        ];
    }

    abstract protected function defineFields(): FieldCollection;

    protected function getBaseFields(): array
    {
        return [];
    }
}
