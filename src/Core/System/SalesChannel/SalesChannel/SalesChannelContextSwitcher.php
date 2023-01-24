<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package core
 */
class SalesChannelContextSwitcher
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractContextSwitchRoute $contextSwitchRoute)
    {
    }

    public function update(DataBag $data, SalesChannelContext $context): void
    {
        $this->contextSwitchRoute->switchContext($data->toRequestDataBag(), $context);
    }
}
