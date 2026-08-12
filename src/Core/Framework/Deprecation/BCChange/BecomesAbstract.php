<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that the method will become abstract in the given version.
 *
 * Call sites are not affected — the method stays callable on all concrete instances. Classes
 * extending the declaring class that rely on the inherited implementation must implement the
 * method themselves before the change happens.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_METHOD)]
final class BecomesAbstract implements ExtenderCompatibilityChange
{
    public function __construct(
        public readonly string $version,
        public readonly ?string $description = null,
    ) {
    }
}
