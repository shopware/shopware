<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class EventDataCollection
{
    public const HIDDEN_FROM_WEBHOOK = 'hiddenFromWebhook';

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $data = [];

    public function add(string $name, EventDataType $type): self
    {
        /** @var array<string, mixed> $options */
        $options = \func_get_args()[2] ?? [];

        $this->data[$name] = [...$type->toArray(), ...$options];

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
