<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Struct\Struct;

class Entity extends Struct
{
    /**
     * @var string
     */
    protected $_uniqueIdentifier;

    /**
     * @var string|null
     */
    protected $versionId;

    /**
     * @var array
     */
    protected $translated = [];

    public function setUniqueIdentifier(string $identifier): void
    {
        $this->_uniqueIdentifier = $identifier;
    }

    public function getUniqueIdentifier(): string
    {
        return $this->_uniqueIdentifier;
    }

    public function getVersionId(): ?string
    {
        return $this->versionId;
    }

    public function setVersionId(string $versionId): void
    {
        $this->versionId = $versionId;
    }

    public function get(string $property)
    {
        if (!$this->has($property)) {
            throw new \InvalidArgumentException(
                sprintf('Property %s do not exist in class %s', $property, \get_class($this))
            );
        }

        return $this->$property;
    }

    public function has(string $property): bool
    {
        return property_exists($this, $property);
    }

    public function getTranslated(): array
    {
        return $this->translated;
    }

    public function setTranslated(array $translated): void
    {
        $this->translated = $translated;
    }

    public function addTranslated(string $key, $value): void
    {
        $this->translated[$key] = $value;
    }
}
