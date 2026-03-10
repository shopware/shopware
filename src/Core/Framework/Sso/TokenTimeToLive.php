<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class TokenTimeToLive
{
    public static function getLowerTTL(\DateInterval $one, \DateInterval $two): \DateInterval
    {
        $start = new \DateTimeImmutable();

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
