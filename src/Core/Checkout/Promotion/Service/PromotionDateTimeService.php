<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\NativeClock;

#[Package('checkout')]
class PromotionDateTimeService implements PromotionDateTimeServiceInterface
{
    /**
     * function returns the actual date time as string
     * in format: Y-m-d H:i:s
     *
     * @throws \Exception
     */
    public function getNow(): string
    {
        return (new NativeClock())->now()->setTimezone(new \DateTimeZone('UTC'))->format(Defaults::STORAGE_DATE_FORMAT);
    }
}
