<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\LanguageLoader;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * Configuration for language data loader.
 *
 * @phpstan-type LanguageLoaderConfigData array{
 *   associations?: list<non-empty-string>
 * }
 *
 * @internal
 */
#[Package('discovery')]
final class LanguageLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @codeCoverageIgnore
     *
     * @param list<non-empty-string> $associations Additional associations to load
     */
    public function __construct(
        public readonly array $associations = [],
    ) {
    }

    public function getDecorated(): AbstractContentDataLoaderConfig
    {
        throw new DecorationPatternException(self::class);
    }
}
