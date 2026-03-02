<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Docs\Script;

use Composer\ClassMapGenerator\ClassMapGenerator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 */
#[Package('framework')]
class ScriptReferenceDataCollector
{
    /**
     * @var array<class-string>
     */
    private static array $classes = [];

    /**
     * @var array<string, SplFileInfo>|null
     */
    private static ?array $files = null;

    /**
     * @return array<class-string>
     */
    public static function getShopwareClasses(): array
    {
        if (self::$classes === []) {
            $generator = new ClassMapGenerator();
            $generator->scanPaths(
                path: __DIR__ . '/../../../..',
                excluded: '/\/vendor\/|\/node_modules\/|\/DevOps\/StaticAnalyze\/|\/Test\/|Interface.php|Trait.php/'
            );
            self::$classes = array_keys($generator->getClassMap()->getMap());
        }

        return self::$classes;
    }

    /**
     * @internal only for testing
     *
     * @param array<class-string> $classes
     */
    public static function setShopwareClasses(array $classes): void
    {
        self::$classes = $classes;
    }

    /**
     * @return SplFileInfo[]
     */
    public static function getFiles(): array
    {
        if (self::$files === null) {
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

    /**
     * @internal only for testing
     *
     * @param array<string, SplFileInfo> $files
     */
    public static function setFiles(array $files): void
    {
        self::$files = $files;
    }

    public static function reset(): void
    {
        self::$files = null;
        self::$classes = [];
    }
}
