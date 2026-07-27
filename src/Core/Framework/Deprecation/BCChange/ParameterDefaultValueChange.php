<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that the default value of a parameter will change in the given version.
 *
 * Call sites omitting the parameter can observe the announced default value instead. Calls that
 * pass the parameter explicitly and overriding declarations are not affected. Tooling can identify
 * call sites that omit the parameter and may need an explicit value to retain their current behavior.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ParameterDefaultValueChange implements CallSiteCompatibilityChange
{
    /**
     * @param string $parameterName the name of the parameter, without the leading `$`
     * @param mixed $newDefaultValue the default value the parameter will use in the announced version
     */
    public function __construct(
        public readonly string $version,
        public readonly string $parameterName,
        public readonly mixed $newDefaultValue,
        public readonly ?string $description = null,
    ) {
    }
}
