<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Account\Order;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;

class AccountOrderPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'account-order.pagelet.loaded';

    /**
     * @var StorefrontSearchResult
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

    public function __construct(
        StorefrontSearchResult $pagelet,
        SalesChannelContext $context,
        InternalRequest $request
    ) {
        $this->pagelet = $pagelet;
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

    public function getPagelet(): StorefrontSearchResult
    {
        return $this->pagelet;
    }

    public function getRequest(): InternalRequest
    {
        return $this->request;
    }
}
