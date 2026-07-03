<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that the type of a parameter will be narrowed in the given version.
 *
 * Callers already passing a value of the announced type do not need to act. Callers relying on
 * the wider current type must adjust before the change happens. Tooling (e.g. Rector) can add
 * type guards or casts at call sites by reading `$newType`.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
#[Package('framework')]
final class ParameterTypeChange implements BCChangeAttribute
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
