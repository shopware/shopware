<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
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

    /**
     * @var \DateTimeInterface|null
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

    /**
     * @var string|null
     */
    private $_entityName;

    public function __get($name)
    {
        return $this->$name;
    }

    public function __set($name, $value): void
    {
        $this->$name = $value;
    }

    public function __isset($name)
    {
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
     * @return mixed|Struct|null
     */
    public function get(string $property)
    {
        if ($this->has($property)) {
            return $this->$property;
        }

        if ($this->hasExtension($property)) {
            return $this->getExtension($property);
        }

        /** @var ArrayStruct|null $extension */
        $extension = $this->getExtension('foreignKeys');
        if ($extension && $extension instanceof ArrayStruct && $extension->has($property)) {
            return $extension->get($property);
        }

        throw new \InvalidArgumentException(
            sprintf('Property %s do not exist in class %s', $property, static::class)
        );
    }

    public function has(string $property): bool
    {
        return property_exists($this, $property);
    }

    public function getTranslated(): array
    {
        return $this->translated;
    }

    public function getTranslation(string $field)
    {
        return $this->translated[$field] ?? null;
    }

    public function setTranslated(array $translated): void
    {
        $this->translated = $translated;
    }

    public function addTranslated(string $key, $value): void
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

    public function getApiAlias(): string
    {
        if ($this->_entityName !== null) {
            return $this->_entityName;
        }

        $class = static::class;
        $class = explode('\\', $class);
        $class = end($class);

        return $this->_entityName = preg_replace(
            '/_entity$/',
            '',
            ltrim(mb_strtolower((string) preg_replace('/[A-Z]/', '_$0', $class)), '_')
        );
    }

    /**
     * @internal
     */
    public function internalSetEntityName(string $entityName): self
    {
        $this->_entityName = $entityName;

        return $this;
    }
}
