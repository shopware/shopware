<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

abstract class AbstractThemePathBuilder
{
    abstract public function getDecorated(): AbstractThemePathBuilder;

    abstract public function assemblePath(string $salesChannelId, string $themeId): string;
}
