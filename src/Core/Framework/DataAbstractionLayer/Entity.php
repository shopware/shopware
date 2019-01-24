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
     * @var static
     */
    protected $viewData;

    /**
     * @var string|null
     */
    protected $versionId;

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
        if (!property_exists($this, $property)) {
            throw new \InvalidArgumentException(
                sprintf('Property %s do not exist in class %s', $property, \get_class($this))
            );
        }

        return $this->$property;
    }

    /**
     * @return static
     */
    public function getViewData()
    {
        return $this->viewData;
    }

    /**
     * @param static $viewData
     */
    public function setViewData(self $viewData): void
    {
        $this->viewData = $viewData;
    }
}
