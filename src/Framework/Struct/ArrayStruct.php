<?php

namespace Shopware\Framework\Struct;

class ArrayStruct extends Struct implements \ArrayAccess
{
    /**
     * @var array
     */
    protected $data;

    public function __construct(array $data)
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

    public function get($key)
    {
        return $this->offsetGet($key);
    }

    public function set($key, $value)
    {
        return $this->offsetSet($key, $value);
    }
}