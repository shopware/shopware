<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that the class is superseded by a feature that is still experimental. Once that
 * feature is stable, the class receives a deprecation annotation and is removed in the
 * given version.
 *
 * The class keeps working as-is and stays covered by the backwards-compatibility promise until
 * then. There is nothing stable to migrate to yet. `$feature` names the experimental feature flag
 * the successor ships behind. If a single class takes over, `$replacement` names it. If a whole
 * domain or a different architecture takes over, `$replacement` stays null and `$description`
 * explains the shift.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ExperimentalReplacement implements CallSiteCompatibilityChange, ExtenderCompatibilityChange
{
    /**
     * @param class-string|null $replacement
     */
    public function __construct(
        public readonly string $version,
        public readonly string $feature,
        public readonly ?string $replacement = null,
        public readonly ?string $description = null,
    ) {
    }
}
