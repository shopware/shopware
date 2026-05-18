<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Clock\NativeClock;

#[Package('fundamentals@after-sales')]
abstract class RuleScope
{
    // @TODO clock-bc: review public ctor change for BC
    public function __construct(
        private readonly ClockInterface $clock = new NativeClock()
    ) {
    }

    abstract public function getContext(): Context;

    abstract public function getSalesChannelContext(): SalesChannelContext;

    public function getCurrentTime(): \DateTimeImmutable
    {
        return $this->clock->now();
    }
}
