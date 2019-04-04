<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Detail\Images;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DetailImagesPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'detail-images.pagelet.loaded';

    /**
     * @var DetailImagesPagelet
     */
    protected $page;

    /**
     * @var SalesChannelContext SalesChannelContext
     */
    protected $context;

    /**
     * @var InternalRequest InternalRequest
     */
    protected $request;

    public function __construct(DetailImagesPagelet $page, SalesChannelContext $context, InternalRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getPage(): DetailImagesPagelet
    {
        return $this->page;
    }

    public function getRequest(): InternalRequest
    {
        return $this->request;
    }
}
