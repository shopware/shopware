<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @package storefront
 */
#[Package('storefront')]
class MD5ThemePathBuilder extends AbstractThemePathBuilder
{
    public function assemblePath(string $salesChannelId, string $themeId): string
    {
        return md5($themeId . $salesChannelId);
    }

    public function getDecorated(): AbstractThemePathBuilder
    {
        throw new DecorationPatternException(self::class);
    }
}
