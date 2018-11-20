<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class ManyToOneAssociationField extends Field implements AssociationInterface
{
    use AssociationTrait;

    public const PRIORITY = 80;

    /**
     * @var string
     */
    protected $referenceField;

    /**
     * @var string
     */
    protected $referenceClass;

    /**
     * @var string
     */
    protected $storageName;

    public function __construct(
        string $propertyName,
        string $storageName,
        string $referenceClass,
        bool $loadInBasic,
        string $referenceField = 'id'
    ) {
        parent::__construct($propertyName);

        $this->loadInBasic = $loadInBasic;
        $this->referenceClass = $referenceClass;
        $this->storageName = $storageName;
        $this->referenceField = $referenceField;
    }

    public function getReferenceField(): string
    {
        return $this->referenceField;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getExtractPriority(): int
    {
        return self::PRIORITY;
    }

    /**
     * @return string|EntityDefinition
     */
    public function getReferenceClass(): string
    {
        return $this->referenceClass;
    }
}
