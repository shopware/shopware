<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

#[Package('storefront')]
class IconCacheTwigFilter extends AbstractExtension
{
    protected static bool $enabled = false;

    /**
     * @var array<string, string|null>
     */
    protected static array $iconCache = [];

    public function getFilters(): array
    {
        return [
            new TwigFilter('sw_icon_cache', $this->iconCache(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sw_icon_cache_enable', $this->enable(...)),
            new TwigFunction('sw_icon_cache_disable', $this->disable(...)),
        ];
    }

    public function iconCache(?string $icon): ?string
    {
        if (!self::$enabled || $icon === null) {
            return $icon;
        }

        preg_match('#id="(.*?)"#', $icon, $iconId);
        if (\count($iconId) === 2 && !empty($iconId[1])) {
            if (isset(self::$iconCache[$iconId[1]])) {
                return self::$iconCache[$iconId[1]];
            }
            self::$iconCache[$iconId[1]] = preg_replace('#<defs>.*</defs>#', '', $icon, 1);
        }

        return $icon;
    }

    public static function flush(): void
    {
        self::$iconCache = [];
    }

    public static function enable(): void
    {
        self::$enabled = true;
        self::flush();
    }

    public static function disable(): void
    {
        self::$enabled = false;
        self::flush();
    }
}
