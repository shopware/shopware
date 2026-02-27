<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\Log\Package;

/**
 * Single source of truth for route path segments and cache tags per section.
 */
#[Package('discovery')]
enum ContentSection: string
{
    case HEADER = 'header';
    case FOOTER = 'footer';
    case MAIN = 'main';

    private const CACHE_TAG_PREFIX = [
        self::HEADER->value => 'header-content-layout-',
        self::FOOTER->value => 'footer-content-layout-',
        self::MAIN->value => 'content-layout-',
    ];

    public function routePathSegment(): string
    {
        return match ($this) {
            self::MAIN => 'content',
            self::HEADER => 'content-header',
            self::FOOTER => 'content-footer',
        };
    }

    public function buildLayoutTag(string $layoutId): string
    {
        return self::CACHE_TAG_PREFIX[$this->value] . $layoutId;
    }

    /**
     * @return list<string>
     */
    public function buildRouteCacheTags(string $layoutId): array
    {
        $tags = [
            self::CACHE_TAG_PREFIX[self::MAIN->value] . $layoutId,
            self::CACHE_TAG_PREFIX[$this->value] . $layoutId,
        ];

        return array_values(array_unique($tags));
    }
}
