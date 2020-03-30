<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class NavigationValidationCategoryIdsLoadedEvent extends NestedEvent
{
    /**
     * @var array
     */
    protected $ids;

    /**
     * @var SalesChannelContext
     */
    protected $salesChannelContext;

    public function __construct(array $ids, SalesChannelContext $salesChannelContext)
    {
        $this->ids = $ids;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getCategoryIds(): array
    {
        return $this->ids;
    }

    public function addCategoryId($id): void
    {
        array_push($this->ids, $id);
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
