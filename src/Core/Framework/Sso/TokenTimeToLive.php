<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
class TokenTimeToLive
{
    public static function getLowerTTL(\DateInterval $one, \DateInterval $two): \DateInterval
    {
        // @TODO clock-static: NativeClock fallback in static helper; review
        $start = (new NativeClock())->now();

        if ($one->invert === 1 && $two->invert === 1) {
            throw SsoException::negativeTimeToLive();
        }

        if ($one->invert === 1) {
            return $two;
        }

        if ($two->invert === 1) {
            return $one;
        }

        return ($start->add($one) < $start->add($two)) ? $one : $two;
    }
}
