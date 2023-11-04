<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
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
     * @var string
     */
    private $_entityName;

    private ?FieldVisibility $_fieldVisibility = null;

    public function __get($name)
    {
        if (FieldVisibility::$isInTwigRenderingContext) {
            $this->checkIfPropertyAccessIsAllowed($name);
        }

        return $this->$name; /* @phpstan-ignore-line */
    }

    public function __set($name, $value): void
    {
        $this->$name = $value; /* @phpstan-ignore-line */
    }

    public function __isset($name)
    {
        if (FieldVisibility::$isInTwigRenderingContext) {
            if (!$this->isPropertyVisible($name)) {
                return false;
            }
        }

        return isset($this->$name); /* @phpstan-ignore-line */
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
        if (FieldVisibility::$isInTwigRenderingContext) {
            $this->checkIfPropertyAccessIsAllowed($property);
        }

        if ($this->has($property)) {
            return $this->$property; /* @phpstan-ignore-line */
        }

        if ($this->hasExtension($property)) {
            return $this->getExtension($property);
        }

        /** @var ArrayStruct<string, mixed>|null $extension */
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
        if (FieldVisibility::$isInTwigRenderingContext) {
            if (!$this->isPropertyVisible($property)) {
                return false;
            }
        }

        return property_exists($this, $property);
    }

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

    /**
     * @return array<mixed>
     */
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

        /** @var string $entityName */
        $entityName = preg_replace(
            '/_entity$/',
            '',
            ltrim(mb_strtolower((string) preg_replace('/[A-Z]/', '_$0', $class)), '_')
        );

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
     */
    protected function checkIfPropertyAccessIsAllowed(string $property): void
    {
        if (!$this->isPropertyVisible($property)) {
            throw new InternalFieldAccessNotAllowedException($property, $this);
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
