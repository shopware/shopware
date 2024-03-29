<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Common\Stubs;

/**
 * @internal
 */
class IniMock
{
    /**
     * @var array<string, string>
     */
    private static array $ini = [];

    /**
     * @param array<string, string> $values
     */
    public static function withIniMock(array $values): void
    {
        self::$ini = $values;
    }

    public static function ini_get(string $name): string|false
    {
        return self::$ini[$name] ?? \ini_get($name);
    }

    /**
     * @param non-empty-string $class
     */
    public static function register(string $class): void
    {
        $self = static::class;

        $nsDivider = strrpos($class, '\\');

        \assert($nsDivider !== false, 'Class name must be fully qualified');

        $mockedNs = [substr($class, 0, $nsDivider)];
        if (strpos($class, '\\Tests\\') > 0) {
            $ns = str_replace('\\Tests\\', '\\', $class);

            $nsDivider = strrpos($ns, '\\');
            \assert($nsDivider !== false, 'Class name must be fully qualified');

            $mockedNs[] = substr($ns, 0, $nsDivider);
        } elseif (str_starts_with($class, 'Tests\\')) {
            $mockedNs[] = substr($class, 6, strrpos($class, '\\') - 6);
        }

        foreach ($mockedNs as $ns) {
            if (\function_exists($ns . '\ini_get')) {
                continue;
            }

            eval(<<<EOPHP
namespace $ns;

function ini_get(\$name)
{
    return \\$self::ini_get(\$name);
}
EOPHP
            );
        }
    }
}
