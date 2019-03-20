<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

class EventDataCollection
{
    /**
     * @var array
     */
    private $data = [];

    public function add(string $name, EventDataType $type): self
    {
        $this->data[$name] = $type->toArray();

        return $this;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
