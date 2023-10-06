<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToManyAssociationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToManyAssociationFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ManyToManyAssociationField extends AssociationField
{
    private ?EntityDefinition $mappingDefinition = null;

    private ?EntityDefinition $toManyDefinition = null;

    public function __construct(
        string $propertyName,
        private string $toManyDefinitionClass,
        string $mappingDefinition,
        private readonly string $mappingLocalColumn,
        private readonly string $mappingReferenceColumn,
        private readonly string $sourceColumn = 'id',
        string $referenceField = 'id'
    ) {
        parent::__construct($propertyName);
        $this->referenceClass = $mappingDefinition;
        $this->referenceField = $referenceField;
    }

    public function getToManyReferenceDefinition(): EntityDefinition
    {
        if ($this->toManyDefinition === null) {
            $this->compileLazy();
        }

        \assert($this->toManyDefinition !== null);

        return $this->toManyDefinition;
    }

    public function getMappingDefinition(): EntityDefinition
    {
        if ($this->mappingDefinition === null) {
            $this->compileLazy();
        }

        \assert($this->mappingDefinition !== null);

        return $this->mappingDefinition;
    }

    public function getMappingLocalColumn(): string
    {
        return $this->mappingLocalColumn;
    }

    public function getMappingReferenceColumn(): string
    {
        return $this->mappingReferenceColumn;
    }

    public function getLocalField(): string
    {
        return $this->sourceColumn;
    }

    protected function getSerializerClass(): string
    {
        return ManyToManyAssociationFieldSerializer::class;
    }

    protected function getResolverClass(): ?string
    {
        return ManyToManyAssociationFieldResolver::class;
    }

    protected function compileLazy(): void
    {
        parent::compileLazy();

        $this->mappingDefinition = $this->getReferenceDefinition();

        \assert($this->registry !== null, 'registry could not be null, because the `compile` method must be called first');
        $this->toManyDefinition = $this->registry->getByClassOrEntityName($this->toManyDefinitionClass);
        $this->toManyDefinitionClass = $this->toManyDefinition->getClass();
    }
}
