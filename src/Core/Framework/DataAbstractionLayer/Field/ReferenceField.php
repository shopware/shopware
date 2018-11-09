<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;

class ReferenceField extends Field implements StorageAware
{
    /**
     * @var string
     */
    private $referenceField;

    /**
     * @var string
     */
    private $referenceClass;

    /**
     * @var string
     */
    private $storageName;

    public function __construct(string $storageName, string $propertyName, string $referenceField, string $referenceClass)
    {
        $this->storageName = $storageName;
        $this->referenceField = $referenceField;
        $this->referenceClass = $referenceClass;
        parent::__construct($propertyName);
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
        return 80;
    }

    public function getReferenceClass(): string
    {
        return $this->referenceClass;
    }
}
