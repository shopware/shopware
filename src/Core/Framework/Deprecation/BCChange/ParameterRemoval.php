<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that a parameter of the method will be removed in the given version.
 *
 * Call sites passing the parameter must stop doing so before the change happens; the
 * `$description` states the replacement (e.g. named arguments). Call sites not passing the
 * parameter are not affected. Classes overriding the method must keep the parameter nullable and
 * optional until the change happens so their declaration remains compatible with the current and
 * announced declaration.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ParameterRemoval implements CallSiteCompatibilityChange, ExtenderCompatibilityChange
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
