<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package sales-channel
 */
class SalesChannelEntityLoadedEvent extends EntityLoadedEvent implements ShopwareSalesChannelEvent
{
    private SalesChannelContext $salesChannelContext;

    /**
     * @param Entity[] $entities
     */
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
