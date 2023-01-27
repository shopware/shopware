<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('system-settings')]
class SystemConfigChangedEvent extends Event
{
    /**
     * @internal
     *
     * @param array|bool|float|int|string|null $value
     */
    public function __construct(
        private readonly string $key,
        private $value,
        private readonly ?string $salesChannelId
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return array|bool|float|int|string|null
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }
}
