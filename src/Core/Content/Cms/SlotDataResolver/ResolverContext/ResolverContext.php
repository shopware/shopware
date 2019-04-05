<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ResolverContext
{
    /**
     * @var SalesChannelContext
     */
    protected $context;

    public function __construct(SalesChannelContext $context)
    {
        $this->context = $context;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
