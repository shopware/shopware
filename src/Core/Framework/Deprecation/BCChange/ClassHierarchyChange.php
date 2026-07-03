<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that the inheritance chain of the class will change in the given version.
 *
 * Classes extending the annotated class, and call sites type-hinting or `instanceof`-checking
 * against one of its current ancestors, must adjust before the change happens if they rely on a
 * part of the hierarchy that goes away. The required `$description` states what will change.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
#[Package('framework')]
final class ClassHierarchyChange implements CallSiteCompatibilityChange, ExtenderCompatibilityChange
{
    public function __construct(
        public readonly string $version,
        public readonly string $description,
    ) {
    }
}
