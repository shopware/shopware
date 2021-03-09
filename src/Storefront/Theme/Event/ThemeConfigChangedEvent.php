<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ThemeConfigChangedEvent extends Event
{
    protected array $config;

    private string $themeId;

    public function __construct(string $themeId, array $config)
    {
        $this->config = $config;
        $this->themeId = $themeId;
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
