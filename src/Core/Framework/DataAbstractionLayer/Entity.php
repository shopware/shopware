<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class Entity extends Struct
{
    /**
     * @deprecated tag:v6.7.0 - Will be natively typed
     *
     * @var string
     */
    protected $_uniqueIdentifier;

    /**
     * @deprecated tag:v6.7.0 - Will be natively typed
     *
     * @var string|null
     */
    protected $versionId;

    /**
     * @deprecated tag:v6.7.0 - Will be natively typed
     *
     * @var array<string, mixed>
     */
    protected $translated = [];

    /**
     * @deprecated tag:v6.7.0 - Will be natively typed
     *
     * @var \DateTimeInterface|null
     */
    protected $createdAt;

    /**
     * @deprecated tag:v6.7.0 - Will be natively typed
     *
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

    /**
     * @deprecated tag:v6.7.0 - Will be natively typed
     *
     * @var string
     */
    private $_entityName;

    private ?FieldVisibility $_fieldVisibility = null;

    /**
     * @deprecated tag:v6.7.0 - reason:exception-change Thrown exception will change from InternalFieldAccessNotAllowedException to DataAbstractionLayerException
     *
     * @param string $name
     *
     * @throws DataAbstractionLayerException|InternalFieldAccessNotAllowedException
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (FieldVisibility::$isInTwigRenderingContext) {
            $this->checkIfPropertyAccessIsAllowed($name);
        }

        return $this->$name;
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value): void
    {
        $this->$name = $value;
    }

    /**
     * @param string $name
     */
    public function __isset($name)
    {
        if (FieldVisibility::$isInTwigRenderingContext) {
            if (!$this->isPropertyVisible($name)) {
                return false;
            }
        }

        return isset($this->$name);
    }

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

    /**
     * @deprecated tag:v6.7.0 - reason:exception-change Thrown exception will change from InternalFieldAccessNotAllowedException to DataAbstractionLayerException
     * @deprecated tag:v6.7.0 - reason:exception-change Thrown exception will change from \InvalidArgumentException to PropertyNotFoundException
     *
     * @throws DataAbstractionLayerException|InternalFieldAccessNotAllowedException
     * @throws PropertyNotFoundException|\InvalidArgumentException
     *
     * @return mixed|Struct|null
     */
    public function get(string $property)
    {
        if (FieldVisibility::$isInTwigRenderingContext) {
            $this->checkIfPropertyAccessIsAllowed($property);
        }

        if ($this->has($property)) {
            return $this->$property;
        }

        if ($this->hasExtension($property)) {
            return $this->getExtension($property);
        }

        $extension = $this->getExtension('foreignKeys');
        if ($extension instanceof ArrayStruct && $extension->has($property)) {
            return $extension->get($property);
        }

        throw DataAbstractionLayerException::propertyNotFound($property, static::class);
    }

    public function has(string $property): bool
    {
        if (FieldVisibility::$isInTwigRenderingContext) {
            if (!$this->isPropertyVisible($property)) {
                return false;
            }
        }

        return property_exists($this, $property);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTranslated(): array
    {
        return $this->translated;
    }

    /**
     * @return mixed|null
     */
    public function getTranslation(string $field)
    {
        return $this->translated[$field] ?? null;
    }

    /**
     * @param array<string, mixed> $translated
     */
    public function setTranslated(array $translated): void
    {
        $this->translated = $translated;
    }

    public function addTranslated(string $key, mixed $value): void
    {
        $this->translated[$key] = $value;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        unset($data['_entityName']);
        unset($data['_fieldVisibility']);

        $data = $this->filterInvisibleFields($data);

        if (!$this->hasExtension('foreignKeys')) {
            return $data;
        }

        $extension = $this->getExtension('foreignKeys');

        if (!$extension instanceof ArrayEntity) {
            return $data;
        }

        foreach ($extension->all() as $key => $value) {
            if (\array_key_exists($key, $data)) {
                continue;
            }
            $data[$key] = $value;
        }

        return $data;
    }

    public function getVars(): array
    {
        $data = parent::getVars();

        return $this->filterInvisibleFields($data);
    }

    public function getApiAlias(): string
    {
        if ($this->_entityName !== null) {
            return $this->_entityName;
        }

        $class = static::class;
        $class = explode('\\', $class);
        $class = end($class);

        $entityName = preg_replace(
            '/_entity$/',
            '',
            ltrim(mb_strtolower((string) preg_replace('/[A-Z]/', '_$0', $class)), '_')
        );
        \assert(\is_string($entityName));

        $this->_entityName = $entityName;

        return $entityName;
    }

    /**
     * @internal
     */
    public function internalSetEntityData(string $entityName, FieldVisibility $fieldVisibility): self
    {
        $this->_entityName = $entityName;
        $this->_fieldVisibility = $fieldVisibility;

        return $this;
    }

    /**
     * @internal
     */
    public function getInternalEntityName(): ?string
    {
        return $this->_entityName;
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function filterInvisibleFields(array $data): array
    {
        if (!$this->_fieldVisibility) {
            return $data;
        }

        return $this->_fieldVisibility->filterInvisible($data);
    }

    /**
     * @internal
     *
     * @deprecated tag:v6.7.0 - reason:exception-change Thrown exception will change from InternalFieldAccessNotAllowedException to DataAbstractionLayerException
     *
     * @throws DataAbstractionLayerException|InternalFieldAccessNotAllowedException
     */
    protected function checkIfPropertyAccessIsAllowed(string $property): void
    {
        if (!$this->isPropertyVisible($property)) {
            throw DataAbstractionLayerException::internalFieldAccessNotAllowed($property, static::class, $this);
        }
    }

    /**
     * @internal
     */
    protected function isPropertyVisible(string $property): bool
    {
        if (!$this->_fieldVisibility) {
            return true;
        }

        return $this->_fieldVisibility->isVisible($property);
    }
}
