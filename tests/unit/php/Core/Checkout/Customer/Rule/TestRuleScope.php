<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package business-ops
 *
 * @internal
 */
class TestRuleScope extends RuleScope
{
    private SalesChannelContext $salesChannelContext;

    public function __construct(SalesChannelContext $salesChannelContext)
    {
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
