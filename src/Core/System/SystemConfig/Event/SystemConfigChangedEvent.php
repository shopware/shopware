<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
class SystemConfigChangedEvent extends Event
{
    /**
     * @internal
     *
     * @param array<string, mixed>|bool|float|int|string|null $value
     */
    public function __construct(
        private readonly string $key,
        private readonly array|bool|float|int|string|null $value,
        private readonly ?string $salesChannelId
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return array<string, mixed>|bool|float|int|string|null
     */
    public function getValue(): array|bool|float|int|string|null
    {
        return $this->value;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }
}
