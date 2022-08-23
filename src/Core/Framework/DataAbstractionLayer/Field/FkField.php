<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FkFieldSerializer;

class FkField extends Field implements StorageAware
{
    public const PRIORITY = 70;

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

    protected DefinitionInstanceRegistry $registry;

    private ?string $referenceEntity = null;

    public function __construct(string $storageName, string $propertyName, string $referenceClass, string $referenceField = 'id')
    {
        $this->referenceClass = $referenceClass;
        $this->storageName = $storageName;
        $this->referenceField = $referenceField;
        parent::__construct($propertyName);
    }

    public function compile(DefinitionInstanceRegistry $registry): void
    {
        $this->registry = $registry;

        parent::compile($registry);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
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

    public function getExtractPriority(): int
    {
        return self::PRIORITY;
    }

    public function getReferenceEntity(): ?string
    {
        $this->compileLazy();

        return $this->referenceEntity;
    }

    protected function getSerializerClass(): string
    {
        return FkFieldSerializer::class;
    }

    protected function compileLazy(): void
    {
        if ($this->referenceDefinition === null) {
            $this->referenceDefinition = $this->registry->getByClassOrEntityName($this->referenceClass);
        }

        if ($this->referenceEntity === null) {
            $this->referenceEntity = $this->referenceDefinition->getEntityName();
        }
    }
}
