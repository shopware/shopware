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

    private static ?string $scanPath = null;

    /**
     * @var list<string>|null
     */
    private static ?array $finderPaths = null;

    /**
     * @return array<class-string>
     */
    public static function getShopwareClasses(): array
    {
        if (self::$classes === []) {
            $generator = new ClassMapGenerator();
            $generator->scanPaths(
                path: self::$scanPath ?? __DIR__ . '/../../../..',
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
     * @internal only for testing
     */
    public static function setScanPath(string $path): void
    {
        self::$scanPath = $path;
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
                ->in(self::$finderPaths ?? [__DIR__ . '/../../../../', __DIR__ . '/../../../../../tests'])
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
     * @param array<string, SplFileInfo> $files
     */
    public static function setFiles(array $files): void
    {
        self::$files = $files;
    }

    /**
     * @param list<string> $paths
     */
    public static function setFinderPaths(array $paths): void
    {
        self::$finderPaths = $paths;
    }

    public static function reset(): void
    {
        self::$files = null;
        self::$classes = [];
        self::$scanPath = null;
        self::$finderPaths = null;
    }
}
