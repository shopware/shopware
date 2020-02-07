<?php declare(strict_types=1);

namespace Shopware\Core\Framework\FeatureFlag;

use Composer\Autoload\ClassMapGenerator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Flag\FEATURE_FLAG;

class FeatureConfig
{
    private static $flags = [];

    /** @var bool */
    private static $initialized = false;

    private static $featureFlagPaths = [
        __DIR__ . '/../../Flag/Flags/',
    ];

    private function __construct()
    {
        //static class only
    }

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        $featureFlagClasses = [];
        foreach (self::$featureFlagPaths as $path) {
            $featureFlagClasses += ClassMapGenerator::createMap($path);
        }
        $featureFlags = [];
        /* @var FEATURE_FLAG $featureFlagClass */
        foreach ($featureFlagClasses as $featureFlagClass => $featurePath) {
            $featureFlags[$featureFlagClass::NAME] = $featureFlagClass::NAME;
        }
        self::$flags = $featureFlags;
        self::$initialized = true;
    }

    public static function getAll(): array
    {
        self::checkInitialization();
        $flagNames = array_keys(self::$flags);
        $resolvedFlags = [];

        foreach ($flagNames as $flagName) {
            $resolvedFlags[$flagName] = self::isActive($flagName);
        }

        return $resolvedFlags;
    }

    public static function isActive(string $flagName): bool
    {
        self::checkInitialization();
        if (!isset(self::$flags[$flagName])) {
            throw new \RuntimeException(sprintf('Unable to retrieve flag %s, not registered', $flagName));
        }

        return ($_SERVER[self::$flags[$flagName]] ?? '') === '1';
    }

    public static function ifActive(string $flagName, \Closure $closure): void
    {
        self::isActive($flagName) && $closure();
    }

    public static function ifActiveCall(string $flagName, $object, string $methodName, ...$arguments): void
    {
        self::checkInitialization();
        $closure = function () use ($object, $methodName, $arguments): void {
            $object->{$methodName}(...$arguments);
        };

        self::ifActive($flagName, \Closure::bind($closure, $object, $object));
    }

    public static function skipTestIfActive(string $flagName, TestCase $test): void
    {
        self::checkInitialization();
        if (!self::isActive($flagName)) {
            return;
        }

        $test::markTestSkipped('Skipping feature test for flag  "' . $flagName . '"');
    }

    public static function addFeatureFlagPaths(string $featureFlagPath): void
    {
        self::$featureFlagPaths[] = $featureFlagPath;
        self::$initialized = false;
        self::init();
    }

    public static function removeFeatureFlagPaths(string $featureFlagPath): void
    {
        self::$featureFlagPaths = array_diff(self::$featureFlagPaths, [$featureFlagPath]);
        self::$initialized = false;
        self::init();
    }

    private static function checkInitialization(): void
    {
        self::init();
        if (self::$initialized) {
            return;
        }

        throw new \Exception('FetureFlags not initialized');
    }
}
