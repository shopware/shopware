<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @deprecated tag:v6.8.0 - will be removed as it is unused. Use AbstractContextSwitchRoute directly.
 */
#[Package('framework')]
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
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(SalesChannelContextSwitcher::class, 'v6.8.0.0', AbstractContextSwitchRoute::class)
        );

        $this->contextSwitchRoute->switchContext($data->toRequestDataBag(), $context);
    }
}
