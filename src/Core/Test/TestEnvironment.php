<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use Shopware\Core\Framework\Log\Package;

/**
 * Environment variables for unit tests. `set()` writes a value to `$_SERVER`, `$_ENV` and the real
 * environment at once, `null` removes it from all three; `reset()` puts back what the test overwrote.
 * The FeatureFlag test extension snapshots the environment before every unit test and restores it
 * afterwards through {@see self::restore()}, so a test never needs its own teardown for this.
 *
 * @internal
 */
#[Package('framework')]
final class TestEnvironment
{
    /**
     * @var array<string, string|int|bool|null>
     */
    private static array $original = [];

    /**
     * @param array<string, string|int|bool|null> $variables
     */
    public static function set(array $variables): void
    {
        foreach ($variables as $name => $value) {
            if (!\array_key_exists($name, self::$original)) {
                self::$original[$name] = $_SERVER[$name] ?? null;
            }

            self::apply($name, $value);
        }
    }

    /**
     * Puts back every variable the test overwrote through {@see self::set()}.
     */
    public static function reset(): void
    {
        foreach (self::$original as $name => $value) {
            self::apply($name, $value);
        }

        self::$original = [];
    }

    /**
     * @return array{env: array<string, mixed>, environment: array<string, string>}
     */
    public static function snapshot(): array
    {
        return ['env' => $_ENV, 'environment' => getenv()];
    }

    /**
     * Restores `$_ENV` and the real environment to a snapshot; `$_SERVER` is the caller's business.
     *
     * @param array{env: array<string, mixed>, environment: array<string, string>} $snapshot
     */
    public static function restore(array $snapshot): void
    {
        $_ENV = $snapshot['env'];

        foreach (array_diff_key(getenv(), $snapshot['environment']) as $name => $value) {
            putenv($name);
        }
        foreach ($snapshot['environment'] as $name => $value) {
            if (getenv($name) !== $value) {
                putenv("{$name}={$value}");
            }
        }

        self::$original = [];
    }

    private static function apply(string $name, string|int|bool|null $value): void
    {
        if ($value === null) {
            // putenv("NAME=") would keep the variable as an empty string in the real environment
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);

            return;
        }

        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;
        putenv(\sprintf('%s=%s', $name, \is_bool($value) ? (int) $value : $value));
    }
}
