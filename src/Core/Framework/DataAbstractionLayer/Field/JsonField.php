<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

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

    /**
     * @var array|null
     */
    protected $default;

    public function __construct(string $storageName, string $propertyName, array $propertyMapping = [], ?array $default = null)
    {
        $this->storageName = $storageName;
        $this->propertyMapping = $propertyMapping;
        $this->default = $default;
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

    public function getDefault(): ?array
    {
        return $this->default;
    }
}
