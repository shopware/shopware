<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0 as it was not used anymore
 */
#[Package('framework')]
class CachedResolvedConfigLoader extends AbstractResolvedConfigLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractResolvedConfigLoader $decorated,
    ) {
    }

    public function getDecorated(): AbstractResolvedConfigLoader
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return $this->decorated;
    }

    public function load(string $themeId, SalesChannelContext $context): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return $this->getDecorated()->load($themeId, $context);
    }

    public static function buildName(string $themeId): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return ThemeConfigCacheInvalidator::buildCacheTag($themeId);
    }
}
