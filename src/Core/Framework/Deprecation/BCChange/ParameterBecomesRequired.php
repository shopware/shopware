<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that a currently optional parameter of the method will become required in the
 * given version.
 *
 * Call sites not passing the parameter must start doing so before the change happens; call
 * sites already passing it are not affected. Classes overriding the method must drop the
 * default value accordingly.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
#[Package('framework')]
final class ParameterBecomesRequired implements CallSiteCompatibilityChange, ExtenderCompatibilityChange
{
    /**
     * @param string $parameterName the name of the parameter, without the leading `$`
     */
    public function __construct(
        public readonly string $version,
        public readonly string $parameterName,
        public readonly ?string $description = null,
    ) {
    }
}
