<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\Hasher;

/**
 * ThemePathBuilder that does not support seeding,
 * this should only be used in projects where recompiling the theme at runtime is not supported (e.g. PaaS) or for testing.
 */
#[Package('storefront')]
class MD5ThemePathBuilder extends AbstractThemePathBuilder
{
    public function assemblePath(string $salesChannelId, string $themeId): string
    {
        return Hasher::hash($themeId . $salesChannelId, 'md5');
    }

    public function generateNewPath(string $salesChannelId, string $themeId, string $seed): string
    {
        return $this->assemblePath($salesChannelId, $themeId);
    }

    public function saveSeed(string $salesChannelId, string $themeId, string $seed): void
    {
        // do nothing, as this implementation does not support seeding
    }

    public function getDecorated(): AbstractThemePathBuilder
    {
        throw new DecorationPatternException(self::class);
    }
}
