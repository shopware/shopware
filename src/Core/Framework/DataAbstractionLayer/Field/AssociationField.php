<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

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

    protected DefinitionInstanceRegistry $registry;

    public function compile(DefinitionInstanceRegistry $registry): void
    {
        $this->registry = $registry;

        parent::compile($registry);
    }

    public function getReferenceDefinition(): EntityDefinition
    {
        $this->compileLazy();

        return $this->referenceDefinition;
    }

    public function getReferenceField(): string
    {
        return $this->referenceField;
    }

    public function getReferenceClass(): string
    {
        $this->compileLazy();

        return $this->referenceClass;
    }

    final public function getAutoload(): bool
    {
        return $this->autoload;
    }

    public function getReferenceEntity(): ?string
    {
        $this->compileLazy();

        return $this->referenceEntity;
    }

    protected function compileLazy(): void
    {
        if ($this->referenceDefinition === null) {
            $this->referenceDefinition = $this->registry->getByClassOrEntityName($this->referenceClass);
        }

        if ($this->referenceEntity === null) {
            $this->referenceEntity = $this->referenceDefinition->getEntityName();
        }

        $this->referenceClass = $this->referenceDefinition->getClass();
    }
}
