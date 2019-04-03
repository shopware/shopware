<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class HeaderPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'header.pagelet.loaded.event';

    /**
     * @var HeaderPagelet
     */
    protected $pagelet;

    /**
     * @var SalesChannelContext
     */
    protected $context;

    /**
     * @var InternalRequest
     */
    protected $request;

    public function __construct(HeaderPagelet $page, SalesChannelContext $context, InternalRequest $request)
    {
        $this->pagelet = $page;
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

    public function getCheckoutContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getPagelet(): HeaderPagelet
    {
        return $this->pagelet;
    }

    public function getRequest(): InternalRequest
    {
        return $this->request;
    }
}
