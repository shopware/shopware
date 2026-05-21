<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
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
        $extract = static function (array $attrs) use ($fields): ?array {
            foreach ($attrs as $attr) {
                /** @var \ReflectionAttribute<object> $attr */
                $props = get_object_vars($attr->newInstance());
                $result = [];
                foreach ($fields as $field) {
                    $result[$field] = $props[$field] ?? null;
                }

                return $result;
            }

            return null;
        };

        return $extract($ref->getAttributes($attributeClass))
            ?? ($ref->hasMethod('__invoke') ? $extract($ref->getMethod('__invoke')->getAttributes($attributeClass)) : null);
    }
}
