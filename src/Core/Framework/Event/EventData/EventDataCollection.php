<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class EventDataCollection
{
    /**
     * @var array<string, EventDataType>
     */
    private array $data = [];

    public function add(string $name, EventDataType $type): self
    {
        $this->data[$name] = $type;

        return $this;
    }

    public function get(string $name): ?EventDataType
    {
        return $this->data[$name] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn ($type) => $type->toArray(), $this->data);
    }

    public function merge(EventDataCollection $collection): EventDataCollection
    {
        $this->data = \array_merge($this->data, $collection->data);

        return $this;
    }
}
