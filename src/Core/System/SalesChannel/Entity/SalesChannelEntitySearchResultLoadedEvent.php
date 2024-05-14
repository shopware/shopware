<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @template TEntityCollection of EntityCollection
 *
 * @extends EntitySearchResultLoadedEvent<TEntityCollection>
 */
#[Package('buyers-experience')]
class SalesChannelEntitySearchResultLoadedEvent extends EntitySearchResultLoadedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @param EntitySearchResult<TEntityCollection> $result
     */
    public function __construct(
        EntityDefinition $definition,
        EntitySearchResult $result,
        private readonly SalesChannelContext $salesChannelContext
    ) {
        parent::__construct($definition, $result);
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
