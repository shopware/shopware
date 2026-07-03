<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that a new optional parameter will be added to the method in the given version.
 *
 * Callers do not need to act. Classes overriding the method must add the parameter with the
 * announced name and type to stay compatible. Tooling can detect conflicts for callers that
 * already pass a named argument with the announced name.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
#[Package('framework')]
final class NewOptionalParameter implements BCChangeAttribute
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
