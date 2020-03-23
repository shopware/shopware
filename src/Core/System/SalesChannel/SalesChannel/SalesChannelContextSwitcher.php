<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelContextSwitcher
{
    /**
     * @var ContextSwitchRoute
     */
    private $contextSwitchRoute;

    public function __construct(ContextSwitchRoute $contextSwitchRoute)
    {
        $this->contextSwitchRoute = $contextSwitchRoute;
    }

    public function update(DataBag $data, SalesChannelContext $context): void
    {
        $this->contextSwitchRoute->switchContext($data->toRequestDataBag(), $context);
    }
}
