<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
final class McpToolAttributeReader
{
    /**
     * Reflects $class for $attributeClass at class level and then on __invoke, returning
     * the requested $fields as an associative array, or null when the class/attribute is absent.
     *
     * @param class-string $attributeClass
     * @param list<string> $fields
     *
     * @return array<string, mixed>|null
     */
    public static function resolveInfo(string $class, string $attributeClass, array $fields): ?array
    {
        if (!class_exists($class)) {
            return null;
        }

        $ref = new \ReflectionClass($class);

        return self::extract($ref->getAttributes($attributeClass), $fields)
            ?? ($ref->hasMethod('__invoke') ? self::extract($ref->getMethod('__invoke')->getAttributes($attributeClass), $fields) : null);
    }

    /**
     * Resolves the first $attributeClass instance declared at class level or on __invoke and
     * returns the attribute object itself (the value object), or null when the class or attribute
     * is absent. Prefer this over resolveInfo() when the caller only needs typed access to the
     * attribute's properties.
     *
     * @template TAttribute of object
     *
     * @param class-string<TAttribute> $attributeClass
     *
     * @return TAttribute|null
     */
    public static function resolveAttribute(string $class, string $attributeClass): ?object
    {
        if (!class_exists($class)) {
            return null;
        }

        $ref = new \ReflectionClass($class);

        $attributes = $ref->getAttributes($attributeClass);
        if ($attributes === [] && $ref->hasMethod('__invoke')) {
            $attributes = $ref->getMethod('__invoke')->getAttributes($attributeClass);
        }

        foreach ($attributes as $attribute) {
            return $attribute->newInstance();
        }

        return null;
    }

    /**
     * @param list<\ReflectionAttribute<object>> $attributes
     * @param list<string> $fields
     *
     * @return array<string, mixed>|null
     */
    private static function extract(array $attributes, array $fields): ?array
    {
        foreach ($attributes as $attribute) {
            $props = get_object_vars($attribute->newInstance());
            $result = [];
            foreach ($fields as $field) {
                $result[$field] = $props[$field] ?? null;
            }

            return $result;
        }

        return null;
    }
}
