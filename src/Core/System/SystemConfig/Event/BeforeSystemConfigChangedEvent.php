<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package system-settings
 */
class BeforeSystemConfigChangedEvent extends Event
{
    private string $key;

    private ?string $salesChannelId;

    /**
     * @var string|float|int|bool|array|null
     */
    private $value;

    /**
     * @param array|bool|float|int|string|null $value
     */
    public function __construct(string $key, $value, ?string $salesChannelId)
    {
        $this->key = $key;
        $this->salesChannelId = $salesChannelId;
        $this->value = $value;
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

    /**
     * @param array|bool|float|int|string|null $value
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
