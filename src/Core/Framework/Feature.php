<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;

class Feature
{
    public const ALL_MAJOR = 'major';

    /**
     * @var array[]
     */
    private static array $registeredFeatures = [];

    public static function normalizeName(string $name): string
    {
        /*
         * Examples:
         * - NEXT-1234
         * - FEATURE_NEXT_1234
         * - SAAS_321
         * - v6.5.0.0 => v6_5_0_0
         */
        return \strtoupper(\str_replace(['.', ':', '-'], '_', $name));
    }

    public static function isActive(string $feature): bool
    {
        $env = EnvironmentHelper::getVariable('APP_ENV', 'prod');
        $feature = self::normalizeName($feature);

        if (self::$registeredFeatures !== []
            && !isset(self::$registeredFeatures[$feature])
            && $env !== 'prod'
        ) {
            trigger_error('Unknown feature "' . $feature . '"', \E_USER_WARNING);
        }

        $featureAll = EnvironmentHelper::getVariable('FEATURE_ALL', '');
        if (self::isTrue((string) $featureAll) && (self::$registeredFeatures === [] || \array_key_exists($feature, self::$registeredFeatures))) {
            if ($featureAll === Feature::ALL_MAJOR) {
                return true;
            }

            // return true if it's registered and not a major feature
            if (isset(self::$registeredFeatures[$feature]) && (self::$registeredFeatures[$feature]['major'] ?? false) === false) {
                return true;
            }
        }

        if (!EnvironmentHelper::hasVariable($feature) && !EnvironmentHelper::hasVariable(\strtolower($feature))) {
            $fallback = self::$registeredFeatures[$feature]['default'] ?? false;

            return (bool) $fallback;
        }

        return self::isTrue(trim((string) EnvironmentHelper::getVariable($feature)));
    }

    public static function ifActive(string $flagName, \Closure $closure): void
    {
        self::isActive($flagName) && $closure();
    }

    public static function ifActiveCall(string $flagName, $object, string $methodName, ...$arguments): void
    {
        $closure = function () use ($object, $methodName, $arguments): void {
            $object->{$methodName}(...$arguments);
        };

        self::ifActive($flagName, \Closure::bind($closure, $object, $object));
    }

    public static function skipTestIfInActive(string $flagName, TestCase $test): void
    {
        if (self::isActive($flagName)) {
            return;
        }

        $test::markTestSkipped('Skipping feature test for flag  "' . $flagName . '"');
    }

    public static function skipTestIfActive(string $flagName, TestCase $test): void
    {
        if (!self::isActive($flagName)) {
            return;
        }

        $test::markTestSkipped('Skipping feature test for flag  "' . $flagName . '"');
    }

    /**
     * Triggers a silenced deprecation notice.
     *
     * @param string $sinceVersion  The version of the package that introduced the deprecation
     * @param string $removeVersion The version of the package when the deprectated code will be removed
     * @param string $message       The message of the deprecation
     * @param mixed  ...$args       Values to insert in the message using printf() formatting
     */
    public static function triggerDeprecated(string $flag, string $sinceVersion, string $removeVersion, string $message, ...$args): void
    {
        $message = 'Deprecated tag:' . $removeVersion . '(flag:' . $flag . '). ' . $message;

        if (self::isActive($flag) || !self::has($flag)) {
            ScriptTraces::addDeprecationNotice(sprintf($message, ...$args));
            trigger_deprecation('shopware/core', $sinceVersion, $message, $args);
        }
    }

    public static function throwException(string $flag, string $message, bool $state = true): void
    {
        if (self::isActive($flag) === $state || !self::has($flag)) {
            throw new \RuntimeException($message);
        }

        ScriptTraces::addDeprecationNotice($message);
    }

    public static function has(string $flag): bool
    {
        $flag = self::normalizeName($flag);

        return isset(self::$registeredFeatures[$flag]);
    }

    public static function getAll(): array
    {
        $resolvedFlags = [];

        foreach (self::$registeredFeatures as $name => $_) {
            $resolvedFlags[$name] = self::isActive($name);
        }

        return $resolvedFlags;
    }

    /**
     * @internal
     */
    public static function registerFeature(string $name, array $metaData = []): void
    {
        $name = self::normalizeName($name);

        // merge with existing data
        $metaData = array_merge(
            self::$registeredFeatures[$name] ?? [],
            $metaData
        );

        // set defaults
        $metaData['major'] = (bool) ($metaData['major'] ?? false);
        $metaData['default'] = (bool) ($metaData['default'] ?? false);
        $metaData['description'] = (string) ($metaData['description'] ?? '');

        self::$registeredFeatures[$name] = $metaData;
    }

    /**
     * @internal
     */
    public static function registerFeatures(iterable $registeredFeatures): void
    {
        foreach ($registeredFeatures as $flag => $data) {
            // old format
            if (\is_string($data)) {
                $flag = $data;
                $data = [];
            }

            self::registerFeature($flag, $data);
        }
    }

    /**
     * @internal
     */
    public static function resetRegisteredFeatures(): void
    {
        self::$registeredFeatures = [];
    }

    /**
     * @internal
     */
    public static function getRegisteredFeatures(): array
    {
        return self::$registeredFeatures;
    }

    private static function isTrue(string $value): bool
    {
        return $value
            && $value !== 'false'
            && $value !== '0'
            && $value !== '';
    }
}
