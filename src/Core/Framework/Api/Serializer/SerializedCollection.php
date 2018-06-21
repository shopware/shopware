<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

class SerializedCollection implements \JsonSerializable
{
    /**
     * @var SerializedEntity[]
     */
    protected $data = [];

    /**
     * @var SerializedEntity[]
     */
    protected $included = [];

    /**
     * @var array
     */
    protected $keyCollection = [];

    /**
     * @var bool
     */
    protected $single = false;

    public function getData(): array
    {
        return $this->data;
    }

    public function getIncluded(): array
    {
        return $this->included;
    }

    public function addData(SerializedEntity $entity): void
    {
        $key = $entity->getId() . '-' . $entity->getType();

        $this->data[$key] = $entity;

        if (isset($this->included[$key])) {
            unset($this->included[$key]);
        }

        $this->keyCollection[$key] = 1;
    }

    public function addIncluded(SerializedEntity $entity): void
    {
        $key = $entity->getId() . '-' . $entity->getType();

        if ($this->contains($entity->getId(), $entity->getType())) {
            return;
        }

        $this->included[$key] = $entity;

        $this->keyCollection[$key] = 1;
    }

    public function get(string $id, string $type): ?SerializedEntity
    {
        $key = $id . '-' . $type;

        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        if (isset($this->included[$key])) {
            return $this->included[$key];
        }

        return null;
    }

    public function contains(string $id, string $type): bool
    {
        $key = $id . '-' . $type;

        return isset($this->keyCollection[$key]);
    }

    public function jsonSerialize()
    {
        $data = get_object_vars($this);

        unset($data['single'], $data['keyCollection']);

        $data['data'] = array_values($data['data']);
        if ($this->isSingle()) {
            $data['data'] = array_shift($data['data']);
        }

        $data['included'] = array_values($data['included']);

        return $data;
    }

    public function isSingle(): bool
    {
        return $this->single;
    }

    public function setSingle(bool $single): void
    {
        $this->single = $single;
    }
}