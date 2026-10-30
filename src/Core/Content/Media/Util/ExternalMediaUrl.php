<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Util;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final class ExternalMediaUrl
{
    public static function isExternal(string $url): bool
    {
        return (bool) preg_match('/^https?:\/\/.+/', $url);
    }

    /**
     * Stricter than isExternal(): also rejects URLs that the filesystem would normalize onto a local path.
     * It builds on isExternal(), because that check is what makes the filesystem code skip external media.
     */
    public static function isStrict(string $url): bool
    {
        if (!self::isExternal($url)) {
            return false;
        }

        // Flysystem turns backslashes into separators and resolves dot segments
        if (str_contains($url, '\\') || stripos($url, '%2e') !== false) {
            return false;
        }

        foreach (explode('/', $url) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }

        $host = parse_url($url, \PHP_URL_HOST);

        return \is_string($host) && $host !== '';
    }
}
