<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that a class moved from `$previousClassName` to the annotated class.
 *
 * The previous name remains available as a runtime class alias until the announced version. Tooling can use this
 * metadata to update references to the canonical class name before the alias is removed.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class ClassMoved implements CallSiteCompatibilityChange, ExtenderCompatibilityChange
{
    public function __construct(
        public readonly string $version,
        public readonly string $previousClassName,
    ) {
    }
}
