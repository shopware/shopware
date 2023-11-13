<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('storefront')]
class ThemeConfigChangedEvent extends Event
{
    public function __construct(
        private readonly string $themeId,
        protected array $config
    ) {
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getThemeId(): string
    {
        return $this->themeId;
    }
}
