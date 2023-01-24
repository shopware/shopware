<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package storefront
 */
class ThemeConfigResetEvent extends Event
{
    public function __construct(private readonly string $themeId)
    {
    }

    public function getThemeId(): string
    {
        return $this->themeId;
    }
}
