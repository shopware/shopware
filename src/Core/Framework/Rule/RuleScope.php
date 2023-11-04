<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('business-ops')]
abstract class RuleScope
{
    abstract public function getContext(): Context;

    abstract public function getSalesChannelContext(): SalesChannelContext;

    public function getCurrentTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
