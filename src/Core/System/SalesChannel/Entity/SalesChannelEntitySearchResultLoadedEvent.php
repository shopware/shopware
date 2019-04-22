<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelEntitySearchResultLoadedEvent extends EntitySearchResultLoadedEvent
{
    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    public function __construct(string $definition, EntitySearchResult $result, SalesChannelContext $salesChannelContext)
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
