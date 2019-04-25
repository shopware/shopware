<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Cms;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CmsPageletLoadedEvent extends NestedEvent
{
    /**
     * @var SalesChannelContext
     */
    private $context;

    /**
     * @var CmsPagelet
     */
    private $page;

    public function __construct(CmsPagelet $page, SalesChannelContext $context)
    {
        $this->context = $context;
        $this->page = $page;
    }

    public function getName(): string
    {
        return 'cms.pagelet.loaded.event';
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCmsPage(): CmsPagelet
    {
        return $this->page;
    }
}
