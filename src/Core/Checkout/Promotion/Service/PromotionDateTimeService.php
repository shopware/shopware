<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Service;

use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\NativeClock;

#[Package('checkout')]
class PromotionDateTimeService implements PromotionDateTimeServiceInterface
{
    /**
     * @internal
     */
    // @TODO clock-bc: review public ctor change for BC
    public function __construct(
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
    }

    /**
     * function returns the actual date time as string
     * in format: Y-m-d H:i:s
     *
     * @throws \Exception
     */
    public function getNow(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format(Defaults::STORAGE_DATE_FORMAT);
    }
}
