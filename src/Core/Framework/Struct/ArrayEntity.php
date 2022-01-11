<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;

class ArrayEntity extends Entity implements \ArrayAccess
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @var string|null
     */
    protected $_entityName = 'array-entity';

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @param string $name
     *
     * @return string|int|float|bool|array|object|null
     */
    public function __get($name)
    {
        if (FieldVisibility::$isInTwigRenderingContext) {
            $this->checkIfPropertyAccessIsAllowed($name);
        }

        return $this->data[$name];
    }

    /**
     * @param string $name
     * @param string|int|float|bool|array|object|null $value
     */
    public function __set($name, $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
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
     * @deprecated tag:v6.5.0 - return type will be changed to bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        if (FieldVisibility::$isInTwigRenderingContext && !$this->isPropertyVisible($offset)) {
            return false;
        }

        return \array_key_exists($offset, $this->data);
    }

    /**
     * @deprecated tag:v6.5.0 - return type will be changed to mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if (FieldVisibility::$isInTwigRenderingContext) {
            $this->checkIfPropertyAccessIsAllowed($offset);
        }

        return $this->data[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    public function get(string $key)
    {
        return $this->offsetGet($key);
    }

    public function set($key, $value)
    {
        return $this->data[$key] = $value;
    }

    public function assign(array $options)
    {
        $this->data = array_replace_recursive($this->data, $options);

        if (\array_key_exists('id', $options)) {
            $this->_uniqueIdentifier = $options['id'];
        }

        return $this;
    }

    public function all()
    {
        return $this->data;
    }

    /**
     * @deprecated tag:v6.5.0 - return type will be changed to mixed
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array/* :mixed */
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
