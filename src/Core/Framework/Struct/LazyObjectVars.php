<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal Prototype helper for PHP 8.4 lazy ghost entities (see LazyEntityFactory)
 *
 * `get_object_vars()` reads every property and would therefore trigger the lazy ghost initializer
 * of partially loaded entities. This helper extracts only the initialized (loaded) properties.
 */
#[Package('framework')]
class LazyObjectVars
{
    /**
     * @return array<string, mixed>
     */
    public static function extract(object $object): array
    {
        $vars = [];

        foreach ((new \ReflectionClass($object))->getProperties() as $property) {
            // @phpstan-ignore method.notFound (PHP 8.4 lazy object API, not known to PHPStan with PHP 8.2 as minimum version)
            if ($property->isStatic() || $property->isPrivate() || $property->isLazy($object)) {
                continue;
            }

            $vars[$property->getName()] = $property->getValue($object);
        }

        return $vars;
    }

    public static function isUninitialized(object $object): bool
    {
        // @phpstan-ignore method.notFound (PHP 8.4 lazy object API, not known to PHPStan with PHP 8.2 as minimum version)
        return (new \ReflectionClass($object))->isUninitializedLazyObject($object);
    }
}
