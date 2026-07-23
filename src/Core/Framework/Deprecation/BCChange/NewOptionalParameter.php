<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that a new optional parameter will be added to the method in the given version.
 *
 * Call sites are not affected — the parameter is optional. Classes overriding the method must
 * add the parameter with the announced name and type to keep a compatible signature once the
 * change happens.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class NewOptionalParameter implements ExtenderCompatibilityChange
{
    /**
     * @param string $parameterName the name of the new parameter, without the leading `$`
     * @param mixed $defaultValue the default value of the new parameter
     */
    public function __construct(
        public readonly string $version,
        public readonly string $parameterName,
        public readonly string $parameterType,
        public readonly mixed $defaultValue = null,
        public readonly ?string $description = null,
    ) {
    }
}
