<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ThemeConfigResetEvent extends Event
{
    private string $themeId;

    public function __construct(string $themeId)
    {
        $this->themeId = $themeId;
    }

    public function getThemeId(): string
    {
        return $this->themeId;
    }
}
