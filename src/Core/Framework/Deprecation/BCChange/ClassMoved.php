<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Deprecation\ClassAliasRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that a class moved from `$previousClassName` to the annotated class.
 *
 * Use this attribute only when the implementation survives under a new fully qualified class name. The previous
 * name must be registered in {@see ClassAliasRegistry::ALIASES} so both names resolve to the same runtime class
 * until the announced version. If the class is a service, register its previous service ID as a deprecated alias
 * of the canonical service as well.
 *
 * Core code must use the canonical name. Tooling uses this metadata to update external references before the alias,
 * attribute, and optional service alias are removed together in the announced version.
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
