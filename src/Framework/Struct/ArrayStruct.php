<?php declare(strict_types=1);

namespace Shopware\Framework\Struct;

use Shopware\Api\Entity\Entity;

class ArrayStruct extends Entity implements \ArrayAccess
{
    /**
     * @var array
     */
    protected $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value)
    {
        return $this->data[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    public function get(string $key)
    {
        return $this->offsetGet($key);
    }

    public function set($key, $value)
    {
        return $this->offsetSet($key, $value);
    }

    public function assign(array $options)
    {
        $this->data = array_replace_recursive($this->data, $options);

        if (array_key_exists('id', $options)) {
            $this->id = $options['id'];
        }

        return $this;
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
