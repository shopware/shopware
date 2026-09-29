<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that the type of a parameter will be widened in the given version.
 *
 * Call sites are not affected — every currently accepted value stays accepted. Classes overriding
 * the method must widen the parameter type of their override to the announced type before the
 * change happens, as a narrower parameter type in the override violates contravariance.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ParameterTypeWidening implements ExtenderCompatibilityChange
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
