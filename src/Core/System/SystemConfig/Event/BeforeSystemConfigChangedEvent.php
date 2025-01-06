<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('services-settings')]
class BeforeSystemConfigChangedEvent extends Event
{
    /**
     * @param array<string, mixed>|bool|float|int|string|null $value
     */
    public function __construct(
        private readonly string $key,
        /** @deprecated tag:v6.7.0 - Will be natively typed */
        private $value,
        private readonly ?string $salesChannelId
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will return native type
     *
     * @return array<string, mixed>|bool|float|int|string|null
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @deprecated tag:v6.7.0 - reason:parameter-name-change - Parameter will be natively typed
     *
     * @param array<string, mixed>|bool|float|int|string|null $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }
}
