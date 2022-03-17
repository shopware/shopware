<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Events;

use Symfony\Contracts\EventDispatcher\Event;

class CmsPageBeforeDefaultChangeEvent extends Event
{
    private string $systemConfigKey;

    private ?string $value;

    private ?string $salesChannelId;

    public function __construct(
        string $systemConfigKey,
        ?string $value,
        ?string $salesChannelId
    ) {
        $this->systemConfigKey = $systemConfigKey;
        $this->value = $value;
        $this->salesChannelId = $salesChannelId;
    }

    public function getSystemConfigKey(): string
    {
        return $this->systemConfigKey;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }
}
