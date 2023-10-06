<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FkFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class FkField extends Field implements StorageAware
{
    final public const PRIORITY = 70;

    /**
     * @var string
     */
    protected $storageName;

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

    protected ?DefinitionInstanceRegistry $registry = null;

    private ?string $referenceEntity = null;

    public function __construct(
        string $storageName,
        string $propertyName,
        string $referenceClass,
        string $referenceField = 'id'
    ) {
        $this->referenceClass = $referenceClass;
        $this->storageName = $storageName;
        $this->referenceField = $referenceField;
        parent::__construct($propertyName);
    }

    public function compile(DefinitionInstanceRegistry $registry): void
    {
        if ($this->registry !== null) {
            return;
        }

        $this->registry = $registry;

        parent::compile($registry);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
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

    public function getExtractPriority(): int
    {
        return self::PRIORITY;
    }

    public function getReferenceEntity(): ?string
    {
        if ($this->referenceEntity === null) {
            $this->compileLazy();
        }

        return $this->referenceEntity;
    }

    protected function getSerializerClass(): string
    {
        return FkFieldSerializer::class;
    }

    protected function compileLazy(): void
    {
        \assert($this->registry !== null, 'registry could not be null, because the `compile` method must be called first');

        $this->referenceDefinition = $this->registry->getByClassOrEntityName($this->referenceClass);
        $this->referenceEntity = $this->referenceDefinition->getEntityName();
    }
}
