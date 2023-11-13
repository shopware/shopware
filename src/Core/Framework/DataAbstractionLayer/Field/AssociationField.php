<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class AssociationField extends Field
{
    /**
     * @var string
     */
    protected $referenceClass;

    /**
     * @var EntityDefinition
     */
    protected $referenceDefinition;

    /**
     * @var string
     */
    protected $referenceField;

    protected bool $autoload = false;

    protected ?string $referenceEntity = null;

    protected ?DefinitionInstanceRegistry $registry = null;

    public function compile(DefinitionInstanceRegistry $registry): void
    {
        if ($this->registry !== null) {
            return;
        }

        $this->registry = $registry;

        parent::compile($registry);
    }

    public function getReferenceDefinition(): EntityDefinition
    {
        if ($this->referenceDefinition === null) {
            $this->compileLazy();
        }

        return $this->referenceDefinition;
    }

    public function getReferenceField(): string
    {
        return $this->referenceField;
    }

    public function getReferenceClass(): string
    {
        if (!\is_subclass_of($this->referenceClass, EntityDefinition::class)) {
            $this->compileLazy();
        }

        return $this->referenceClass;
    }

    final public function getAutoload(): bool
    {
        return $this->autoload;
    }

    public function getReferenceEntity(): ?string
    {
        if ($this->referenceEntity === null) {
            $this->compileLazy();
        }

        return $this->referenceEntity;
    }

    protected function compileLazy(): void
    {
        \assert($this->registry !== null, 'registry could not be null, because the `compile` method must be called first');

        $this->referenceDefinition = $this->registry->getByClassOrEntityName($this->referenceClass);
        $this->referenceClass = $this->referenceDefinition->getClass();
        $this->referenceEntity = $this->referenceDefinition->getEntityName();
    }
}
