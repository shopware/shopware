<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that the inheritance chain of the class will change in the given version.
 *
 * Callers do not need to act. Extension code extending the class or type-hinting against one
 * of its current ancestors may be affected and should be flagged by tooling. The required
 * `$description` states what will change in the hierarchy.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
#[Package('framework')]
final class ClassHierarchyChange implements BCChangeAttribute
{
    public function __construct(
        public readonly string $version,
        public readonly string $description,
    ) {
    }
}
