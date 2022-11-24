<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package sales-channel
 *
 * @internal
 */
class PartialSalesChannelEntityLoadedEvent extends PartialEntityLoadedEvent implements ShopwareSalesChannelEvent
{
    private SalesChannelContext $salesChannelContext;

    public function __construct(EntityDefinition $definition, array $entities, SalesChannelContext $context)
    {
        parent::__construct($definition, $entities, $context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        return 'sales_channel.' . parent::getName();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
