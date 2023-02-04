<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('storefront')]
class ThemeAssignedEvent extends Event
{
    public function __construct(
        private readonly string $themeId,
        private readonly string $salesChannelId
    ) {
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
