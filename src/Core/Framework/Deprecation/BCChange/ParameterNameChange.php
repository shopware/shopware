<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that a parameter of the method will be renamed in the given version.
 *
 * Only callers using named arguments for this parameter are affected. Tooling (e.g. Rector)
 * can rename the named argument at all call sites by reading `$parameterName` and `$newName`.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
#[Package('framework')]
final class ParameterNameChange implements BCChangeAttribute
{
    /**
     * @param string $parameterName the current name of the parameter, without the leading `$`
     * @param string $newName the future name of the parameter, without the leading `$`
     */
    public function __construct(
        public readonly string $version,
        public readonly string $parameterName,
        public readonly string $newName,
        public readonly ?string $description = null,
    ) {
    }
}
