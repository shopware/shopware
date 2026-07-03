<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that the order of the method's parameters will change in the given version.
 *
 * Call sites using named arguments are not affected. Positional call sites and classes
 * overriding the method must adjust to the announced order before the change happens.
 * Tooling (e.g. Rector) can reorder positional arguments by reading `$newOrder`.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
#[Package('framework')]
final class ParameterOrderChange implements CallSiteCompatibilityChange, ExtenderCompatibilityChange
{
    /**
     * @param list<string> $newOrder the announced parameter order, names without the leading `$`
     */
    public function __construct(
        public readonly string $version,
        public readonly array $newOrder,
        public readonly ?string $description = null,
    ) {
    }
}
