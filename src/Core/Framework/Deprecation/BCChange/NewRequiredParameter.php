<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that a new required parameter will be added to the method in the given version.
 *
 * Call sites must start passing the parameter before the change happens; PHP accepts the
 * extra argument today, and implementations read it via `func_get_arg()` until the next
 * major. Classes overriding the method cannot declare the parameter yet (an additional
 * required parameter is signature-incompatible with the current declaration) and must use
 * the same argument shim until the change happens.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class NewRequiredParameter implements CallSiteCompatibilityChange, ExtenderCompatibilityChange
{
    /**
     * @param string $parameterName the name of the new parameter, without the leading `$`
     */
    public function __construct(
        public readonly string $version,
        public readonly string $parameterName,
        public readonly string $parameterType,
        public readonly ?string $description = null,
    ) {
    }
}
