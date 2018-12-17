<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;

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
     * @var string
     */
    protected $referenceField;

    public function __construct(string $storageName, string $propertyName, string $referenceClass)
    {
        $this->referenceClass = $referenceClass;
        $this->storageName = $storageName;
        $this->referenceField = 'id';
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getReferenceClass(): string
    {
        return $this->referenceClass;
    }

    public function getReferenceField(): string
    {
        return $this->referenceField;
    }

    public function getExtractPriority(): int
    {
        return self::PRIORITY;
    }
}
