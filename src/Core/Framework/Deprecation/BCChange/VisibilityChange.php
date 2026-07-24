<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that the visibility of the method will be reduced in the given version.
 *
 * Call sites outside the announced visibility scope must stop calling the method before the
 * change happens. Overrides in extending classes must not declare a wider visibility than the
 * announced one once the change happens.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_METHOD)]
final class VisibilityChange implements CallSiteCompatibilityChange, ExtenderCompatibilityChange
{
    /**
     * @param 'protected'|'private' $newVisibility
     */
    public function __construct(
        public readonly string $version,
        public readonly string $newVisibility,
        public readonly ?string $description = null,
    ) {
    }
}
