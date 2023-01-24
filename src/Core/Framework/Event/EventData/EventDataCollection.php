<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

/**
 * @package business-ops
 */
class EventDataCollection
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $data = [];

    public function add(string $name, EventDataType $type): self
    {
        $this->data[$name] = $type->toArray();

        return $this;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
