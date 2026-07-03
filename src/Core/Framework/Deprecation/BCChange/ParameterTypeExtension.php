<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that the type of a parameter will be widened in the given version.
 *
 * Callers do not need to act. Classes overriding the method must widen the parameter type
 * accordingly to stay compatible with the announced type.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
#[Package('framework')]
final class ParameterTypeExtension implements BCChangeAttribute
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
