<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Event;

use Symfony\Contracts\EventDispatcher\Event;

class SystemConfigChangedEvent extends Event
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var string|float|int|bool|array|null
     */
    private $value;

    /**
     * @var string|null
     */
    private $salesChannelId;

    /**
     * @param array|bool|float|int|string|null $value
     */
    public function __construct(string $key, $value, ?string $salesChannelId)
    {
        $this->key = $key;
        $this->value = $value;
        $this->salesChannelId = $salesChannelId;
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
