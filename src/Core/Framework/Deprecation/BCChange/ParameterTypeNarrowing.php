<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that the type of a parameter will be narrowed in the given version.
 *
 * Call sites passing values that are not covered by the announced type must adjust before the
 * change happens; call sites already passing the announced type are not affected. Overrides may
 * keep the wider parameter type (contravariance). Tooling (e.g. Rector) can add type guards or
 * casts at affected call sites by reading `$newType`.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ParameterTypeNarrowing implements CallSiteCompatibilityChange
{
    /**
     * @param string $parameterName the name of the parameter, without the leading `$`
     */
    public function __construct(
        public readonly string $version,
        public readonly string $parameterName,
        public readonly string $newType,
        public readonly ?string $description = null,
    ) {
    }
}
