<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that a parameter of the method will be renamed in the given version.
 *
 * Call sites passing the parameter as a named argument must switch to the announced name before
 * the change happens; positional call sites are not affected. Tooling (e.g. Rector) can rename
 * the named argument at all call sites by reading `$parameterName` and `$newName`.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ParameterNameChange implements CallSiteCompatibilityChange
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
