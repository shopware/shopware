<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;

/**
 * @phpstan-type FeatureFlagConfig array{name?: string, default?: boolean, major?: boolean, description?: string, active?: bool, static?: bool}
 */
#[Package('core')]
class Feature
{
    final public const ALL_MAJOR = 'major';

    /**
     * @var array<bool>
     */
    private static array $silent = [];

    /**
     * @var array<string, FeatureFlagConfig>
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

    /**
     * @template TReturn of mixed
     *
     * @param array<string> $features
     * @param \Closure(): TReturn $closure
     *
     * @return TReturn
     */
    public static function fake(array $features, \Closure $closure)
    {
        $before = self::$registeredFeatures;
        $serverVarsBackup = $_SERVER;

        $result = null;

        try {
            self::$registeredFeatures = [];
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'v6.') || str_starts_with($key, 'FEATURE_') || str_starts_with($key, 'V6_')) {
                    // set to false so that $_ENV is not checked
                    $_SERVER[$key] = false;
                }
            }

            if ($features) {
                foreach ($features as $feature) {
                    $_SERVER[Feature::normalizeName($feature)] = true;
                }
            }

            $result = $closure();
        } finally {
            self::$registeredFeatures = $before;
            $_SERVER = $serverVarsBackup;
        }

        return $result;
    }

    /**
     * Determines weather a feature is active or not.
     *
     * A feature is either active by being in the environment (specified in the .env file for example)
     * or by matching a FEATURE_ALL mode.
     *
     * With FEATURE_ALL you can activate either all minor or all major features.
     * FEATURE_ALL=1, FEATURE_ALL=minor or any other truthy values except 'false' equals minor
     * FEATURE_ALL=major puts it into major mode
     *
     * The specific feature configuration in the environment is always the highest priority, no matter the FEATURE_ALL configuration.
     */
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

        // Specific configurations are higher priority then FEATURE_ALL
        if (self::featureInEnv($feature)) {
            return self::getFeatureInEnv($feature);
        }

        $featureAll = EnvironmentHelper::getVariable('FEATURE_ALL', '');

        // If FEATURE_ALL has any truthy value
        if (self::isTrue((string) $featureAll) && (self::$registeredFeatures === [] || \array_key_exists($feature, self::$registeredFeatures))) {
            // If feature is not major and is have set active, return the active state
            if (!self::getConfiguration($feature, 'major') && self::hasConfiguration($feature, 'active')) {
                return self::getConfiguration($feature, 'active');
            }

            // Should only enable major flags
            if ($featureAll === Feature::ALL_MAJOR) {
                return self::getConfiguration($feature, 'major');
            }

            // Enable all minor flags
            if (!self::getConfiguration($feature, 'major')) {
                return true;
            }
        }

        if (self::hasConfiguration($feature, 'active')) {
            return self::getConfiguration($feature, 'active');
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

    public static function setActive(string $feature, bool $active): void
    {
        $feature = self::normalizeName($feature);

        if (!isset(self::$registeredFeatures[$feature])) {
            throw FeatureException::featureNotRegistered($feature);
        }

        self::$registeredFeatures[$feature]['active'] = $active;
    }

    public static function ifNotActive(string $flagName, \Closure $closure): void
    {
        !self::isActive($flagName) && $closure();
    }

    public static function callSilentIfInactive(string $flagName, \Closure $closure): void
    {
        $before = isset(self::$silent[$flagName]);
        self::$silent[$flagName] = true;

        try {
            if (!self::isActive($flagName)) {
                $closure();
            }
        } finally {
            if (!$before) {
                unset(self::$silent[$flagName]);
            }
        }
    }

    /**
     * @template TReturn of mixed
     *
     * @param \Closure(): TReturn $closure
     *
     * @return TReturn
     */
    public static function silent(string $flagName, \Closure $closure): mixed
    {
        $before = isset(self::$silent[$flagName]);
        self::$silent[$flagName] = true;

        try {
            $result = $closure();
        } finally {
            if (!$before) {
                unset(self::$silent[$flagName]);
            }
        }

        return $result;
    }

    public static function skipTestIfInActive(string $flagName, TestCase $test): void
    {
        if (self::isActive($flagName)) {
            return;
        }

        $test->markTestSkipped('Skipping feature test for flag  "' . $flagName . '"');
    }

    public static function skipTestIfActive(string $flagName, TestCase $test): void
    {
        if (!self::isActive($flagName)) {
            return;
        }

        $test->markTestSkipped('Skipping feature test for flag  "' . $flagName . '"');
    }

    public static function throwException(string $flag, string $message, bool $state = true): void
    {
        if (self::isActive($flag) === $state || (self::$registeredFeatures !== [] && !self::has($flag))) {
            throw FeatureException::error($message);
        }

        if (\PHP_SAPI !== 'cli') {
            ScriptTraces::addDeprecationNotice($message);
        }
    }

    public static function triggerDeprecationOrThrow(string $majorFlag, string $message): void
    {
        if (!empty(self::$silent[$majorFlag])) {
            return;
        }

        if (self::isActive($majorFlag) || (self::$registeredFeatures !== [] && !self::has($majorFlag))) {
            throw FeatureException::error('Tried to access deprecated functionality: ' . $message);
        }

        if (\PHP_SAPI !== 'cli') {
            ScriptTraces::addDeprecationNotice($message);
        }

        trigger_deprecation('shopware/core', '', $message);
    }

    public static function deprecatedMethodMessage(string $class, string $method, string $majorVersion, ?string $replacement = null): string
    {
        $fullQualifiedMethodName = sprintf('%s::%s', $class, $method);
        if (str_contains($method, '::')) {
            $fullQualifiedMethodName = $method;
        }

        $message = \sprintf(
            'Method "%s()" is deprecated and will be removed in %s.',
            $fullQualifiedMethodName,
            $majorVersion
        );

        if ($replacement) {
            $message = \sprintf('%s Use "%s" instead.', $message, $replacement);
        }

        return $message;
    }

    public static function deprecatedClassMessage(string $class, string $majorVersion, ?string $replacement = null): string
    {
        $message = \sprintf(
            'Class "%s" is deprecated and will be removed in %s.',
            $class,
            $majorVersion
        );

        if ($replacement) {
            $message = \sprintf('%s Use "%s" instead.', $message, $replacement);
        }

        return $message;
    }

    public static function has(string $flag): bool
    {
        $flag = self::normalizeName($flag);

        return isset(self::$registeredFeatures[$flag]);
    }

    /**
     * @return array<string, bool>
     */
    public static function getAll(bool $denormalized = true): array
    {
        $resolvedFlags = [];

        foreach (self::$registeredFeatures as $name => $_) {
            $active = self::isActive($name);
            $resolvedFlags[$name] = $active;

            if (!$denormalized) {
                continue;
            }
            $resolvedFlags[self::denormalize($name)] = $active;
        }

        return $resolvedFlags;
    }

    /**
     * @param FeatureFlagConfig $metaData
     *
     * @internal
     */
    public static function registerFeature(string $name, array $metaData = []): void
    {
        $name = self::normalizeName($name);

        // merge with existing data

        /** @var FeatureFlagConfig $metaData */
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
     * @param array<string, FeatureFlagConfig>|list<string> $registeredFeatures
     *
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

            self::registerFeature((string) $flag, $data);
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
     *
     * @return array<string, FeatureFlagConfig>
     */
    public static function getRegisteredFeatures(): array
    {
        return self::$registeredFeatures;
    }

    private static function isTrue(string $value): bool
    {
        return $value && $value !== 'false';
    }

    private static function denormalize(string $name): string
    {
        return \strtolower(\str_replace(['_'], '.', $name));
    }

    private static function hasConfiguration(string $feature, string $key): bool
    {
        return \array_key_exists($feature, self::$registeredFeatures) && \array_key_exists($key, self::$registeredFeatures[$feature]);
    }

    private static function getConfiguration(string $feature, string $key): bool
    {
        if (!self::hasConfiguration($feature, $key)) {
            return false;
        }

        return (bool) (self::$registeredFeatures[$feature][$key] ?? false);
    }

    private static function featureInEnv(string $feature): bool
    {
        return EnvironmentHelper::hasVariable($feature) || EnvironmentHelper::hasVariable(\strtolower($feature));
    }

    private static function getFeatureInEnv(string $feature): bool
    {
        if (EnvironmentHelper::hasVariable($feature)) {
            return self::isTrue((string) EnvironmentHelper::getVariable($feature));
        }

        if (EnvironmentHelper::hasVariable(\strtolower($feature))) {
            return self::isTrue((string) EnvironmentHelper::getVariable(\strtolower($feature)));
        }

        return false;
    }
}
