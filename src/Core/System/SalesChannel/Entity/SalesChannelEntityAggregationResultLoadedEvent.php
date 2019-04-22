<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregatorResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelEntityAggregationResultLoadedEvent extends EntityAggregationResultLoadedEvent
{
    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    public function __construct(string $definition, AggregatorResult $result, SalesChannelContext $salesChannelContext)
    {
        parent::__construct($definition, $result);
        $this->salesChannelContext = $salesChannelContext;
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
