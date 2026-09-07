<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Feature;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * Throwaway stand-in for a feature built for the next major: one gated behaviour switch and one
 * method deprecated for that major, the two shapes a major actually takes in the code base. It
 * exists to prove in CI that a major's code path is only live in its own test lane.
 *
 * @internal
 */
#[Package('framework')]
class MajorLaneProbe
{
    public function render(): string
    {
        if (!Feature::isActive('v6.9.0.0')) {
            return 'legacy';
        }

        return 'v6.9';
    }

    /**
     * @deprecated tag:v6.9.0 - Will be removed, use render() instead
     */
    public function renderLegacy(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.9.0', 'render()')
        );

        return 'legacy';
    }
}
