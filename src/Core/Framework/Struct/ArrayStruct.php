<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

class ArrayStruct extends Struct implements \ArrayAccess
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @var string|null
     */
    protected $apiAlias;

    public function __construct(array $data = [], ?string $apiAlias = null)
    {
        $this->data = $data;
        $this->apiAlias = $apiAlias;
    }

    public function has(string $property): bool
    {
        return \array_key_exists($property, $this->data);
    }

    public function offsetExists($offset)
    {
        return \array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset)
    {
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

    /**
     * @param string|int $key
     * @param array      $value
     *
     * @return array
     */
    public function set($key, $value)
    {
        return $this->data[$key] = $value;
    }

    public function assign(array $options)
    {
        $this->data = array_replace_recursive($this->data, $options);

        return $this;
    }

    public function all()
    {
        return $this->data;
    }

    public function jsonSerialize(): array
    {
        $jsonArray = parent::jsonSerialize();

        // The key-values pairs from the property $data are now serialized in the JSON property "data". But the
        // key-value pairs from data should appear in the serialization as they were properties of the ArrayStruct
        // itself. Therefore the key-values moved one level up.
        unset($jsonArray['data']);
        $data = $this->data;
        $this->convertDateTimePropertiesToJsonStringRepresentation($data);

        return array_merge($jsonArray, $data);
    }

    public function getApiAlias(): string
    {
        return $this->apiAlias ?? 'array_struct';
    }
}
