<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements \ArrayAccess<string, mixed>
 */
#[Package('core')]
class ArrayEntity extends Entity implements \ArrayAccess
{
    protected ?string $_entityName = 'array-entity';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(protected array $data = [])
    {
    }

    /**
     * @param string $name
     */
    public function __get($name): mixed
    {
        if (FieldVisibility::$isInTwigRenderingContext) {
            $this->checkIfPropertyAccessIsAllowed($name);
        }

        return $this->data[$name];
    }

    /**
     * @param string $name
     */
    public function __set($name, mixed $value): void
    {
        if ($name === 'id') {
            $this->_uniqueIdentifier = $value;
        }
        $this->data[$name] = $value;
    }

    /**
     * @param string $name
     */
    public function __isset($name): bool
    {
        if (FieldVisibility::$isInTwigRenderingContext && !$this->isPropertyVisible($name)) {
            return false;
        }

        return isset($this->data[$name]);
    }

    public function has(string $property): bool
    {
        return \array_key_exists($property, $this->data);
    }

    public function getUniqueIdentifier(): string
    {
        if (!$this->_uniqueIdentifier) {
            return $this->data['id'];
        }

        return parent::getUniqueIdentifier();
    }

    public function getId(): string
    {
        return $this->data['id'];
    }

    /**
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        if (FieldVisibility::$isInTwigRenderingContext && !$this->isPropertyVisible($offset)) {
            return false;
        }

        return \array_key_exists($offset, $this->data);
    }

    /**
     * @param string $offset
     */
    public function offsetGet($offset): mixed
    {
        if (FieldVisibility::$isInTwigRenderingContext) {
            $this->checkIfPropertyAccessIsAllowed($offset);
        }

        return $this->data[$offset] ?? null;
    }

    /**
     * @param string $offset
     */
    public function offsetSet($offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    /**
     * @param string $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    public function get(string $key): mixed
    {
        return $this->offsetGet($key);
    }

    /**
     * @param string $key
     */
    public function set($key, mixed $value): mixed
    {
        return $this->data[$key] = $value;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function assign(array $options)
    {
        $this->data = array_replace_recursive($this->data, $options);

        if (\array_key_exists('id', $options)) {
            $this->_uniqueIdentifier = $options['id'];
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVars(): array
    {
        $vars = parent::getVars();

        unset($vars['data']);

        return array_merge($vars, $this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $jsonArray = parent::jsonSerialize();

        // The key-values pairs from the property $data are now serialized in the JSON property "data". But the
        // key-value pairs from data should appear in the serialization as they were properties of the ArrayEntity
        // itself. Therefore the key-values moved one level up.
        unset($jsonArray['data'], $jsonArray['createdAt'], $jsonArray['updatedAt'], $jsonArray['versionId']);
        $data = $this->data;
        $this->convertDateTimePropertiesToJsonStringRepresentation($data);

        return array_merge($jsonArray, $data);
    }
}
