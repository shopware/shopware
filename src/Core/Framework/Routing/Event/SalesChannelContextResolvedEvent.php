<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Event;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class SalesChannelContextResolvedEvent extends Event
{
    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    public function __construct(SalesChannelContext $salesChannelContext)
    {
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
