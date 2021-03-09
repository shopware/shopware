<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ThemeAssignedEvent extends Event
{
    private string $themeId;

    private string $salesChannelId;

    public function __construct(string $themeId, string $salesChannelId)
    {
        $this->themeId = $themeId;
        $this->salesChannelId = $salesChannelId;
    }

    public function getThemeId(): string
    {
        return $this->themeId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }
}
