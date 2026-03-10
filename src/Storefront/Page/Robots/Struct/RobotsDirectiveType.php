<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore - Enum is tested indirectly through RobotsDirective and parser tests
 */
#[Package('framework')]
enum RobotsDirectiveType: string
{
    case ALLOW = 'Allow';
    case DISALLOW = 'Disallow';
    case CRAWL_DELAY = 'Crawl-delay';
    case SITEMAP = 'Sitemap';
    case REQUEST_RATE = 'Request-rate';
    case VISIT_TIME = 'Visit-time';
    case HOST = 'Host';
    case USER_AGENT = 'User-agent';

    /**
     * Returns whether this directive type is path-based (requires domain prefix).
     */
    public function isPathBased(): bool
    {
        return match ($this) {
            self::ALLOW, self::DISALLOW => true,
            default => false,
        };
    }

    /**
     * Parses a case-insensitive string into a directive type.
     */
    public static function tryFromInsensitive(string $value): ?self
    {
        return self::tryFrom(ucfirst(mb_strtolower($value)));
    }
}
