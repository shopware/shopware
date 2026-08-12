<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Marker interface for all BC-change attributes.
 *
 * BC-change attributes document planned changes to the public API surface that are *not* deprecations:
 * there is no replacement API to migrate to and the annotated symbol keeps working as-is. Whether
 * third-party code has to act before the announced version depends on how it uses the symbol; the
 * sub-interfaces state which audience can be affected:
 *
 * - {@see CallSiteCompatibilityChange} — code calling the symbol can be affected
 * - {@see ExtenderCompatibilityChange} — code extending or overriding the symbol can be affected
 *
 * These attributes replace the former `reason:*` deprecation PHPDoc markers for such changes,
 * which incorrectly surfaced as deprecation errors in static analysis of third-party code.
 * Use a real deprecation annotation (together with `Feature::triggerDeprecationOrThrow()`) only
 * when functionality is removed or replaced and extension developers have to migrate.
 *
 * Tooling can discover all BC-change attributes on a symbol via reflection:
 * `$reflection->getAttributes(BCChangeAttribute::class, \ReflectionAttribute::IS_INSTANCEOF)`
 *
 * Every implementation exposes a public `$version` property containing the Shopware version tag
 * (e.g. `'v6.8.0'`) in which the announced change will happen.
 */
#[Package('framework')]
interface BCChangeAttribute
{
}
