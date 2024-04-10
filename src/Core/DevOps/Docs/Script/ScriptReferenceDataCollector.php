<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Docs\Script;

use League\ConstructFinder\ConstructFinder;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 */
#[Package('core')]
class ScriptReferenceDataCollector
{
    /**
     * @var array<class-string>
     */
    private static array $classes = [];

    /**
     * @var array<string, SplFileInfo>
     */
    private static array $files = [];

    /**
     * @return array<class-string>
     */
    public static function getShopwareClasses(): array
    {
        if (self::$classes === []) {
            self::$classes = ConstructFinder::locatedIn(__DIR__ . '/../../../..')
                ->exclude('*/Test/*', '*/vendor/*', '*/DevOps/StaticAnalyze*', 'node_modules')
                ->findClassNames();
        }

        return self::$classes;
    }

    /**
     * @return SplFileInfo[]
     */
    public static function getFiles(): array
    {
        if (self::$files === []) {
            $finder = new Finder();
            $finder
                ->files()
                ->in([__DIR__ . '/../../../../', __DIR__ . '/../../../../../tests'])
                // exclude js files including node_modules for performance reasons, filtering with `notPath`, etc. has no performance impact
                // note that excluded paths need to be relative to platform/src and that no wildcards are supported
                ->exclude([
                    'Administration/Resources',
                    'Storefront/Resources',
                    'Recovery',
                ])
                ->ignoreUnreadableDirs();

            self::$files = iterator_to_array($finder);
        }

        return self::$files;
    }

    public static function reset(): void
    {
        self::$files = [];
        self::$classes = [];
    }
}
