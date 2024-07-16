<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('services-settings')]
class BeforeSystemConfigMultipleChangedEvent extends Event
{
    /**
     * @param array<string, array<mixed>|bool|float|int|string|null> $config
     */
    public function __construct(
        private array $config,
        private readonly ?string $salesChannelId
    ) {
    }

    /**
     * @return array<string, array<mixed>|bool|float|int|string|null>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<mixed>|bool|float|int|string|null $value
     */
    public function setValue(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }
}
