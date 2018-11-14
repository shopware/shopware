<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;

class JsonField extends Field implements StorageAware
{
    /**
     * @var string
     */
    protected $storageName;

    /**
     * @var Field[]
     */
    protected $propertyMapping;

    public function __construct(string $storageName, string $propertyName, array $propertyMapping = [])
    {
        $this->storageName = $storageName;
        $this->propertyMapping = $propertyMapping;
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * @return Field[]
     */
    public function getPropertyMapping(): array
    {
        return $this->propertyMapping;
    }
}
