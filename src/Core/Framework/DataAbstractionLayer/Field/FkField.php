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

    public function __construct(string $storageName, string $propertyName, string $referenceClass, string $referenceField = 'id')
    {
        $this->referenceClass = $referenceClass;
        $this->storageName = $storageName;
        $this->referenceField = $referenceField;
        parent::__construct($propertyName);
    }

    public function compile(DefinitionInstanceRegistry $registry): void
    {
        if ($this->referenceDefinition !== null) {
            return;
        }

        parent::compile($registry);

        $this->referenceDefinition = $registry->get($this->referenceClass);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getReferenceDefinition(): EntityDefinition
    {
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

    protected function getSerializerClass(): string
    {
        return FkFieldSerializer::class;
    }
}
