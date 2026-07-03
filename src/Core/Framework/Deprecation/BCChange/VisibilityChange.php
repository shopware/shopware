<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that the visibility of the method will be reduced in the given version.
 *
 * Callers inside the announced visibility scope do not need to act. Extension code calling the
 * method from outside the announced scope must stop doing so before the change happens and
 * should be flagged by tooling.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
#[Package('framework')]
final class VisibilityChange implements BCChangeAttribute
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
