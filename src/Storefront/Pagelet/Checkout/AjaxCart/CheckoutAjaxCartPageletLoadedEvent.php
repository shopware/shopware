<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Checkout\AjaxCart;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class CheckoutAjaxCartPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'checkout-info.pagelet.loaded';

    /**
     * @var CheckoutAjaxCartPagelet
     */
    protected $pagelet;

    /**
     * @var SalesChannelContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(
        CheckoutAjaxCartPagelet $pagelet,
        SalesChannelContext $context,
        Request $request
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

    public function getPagelet(): CheckoutAjaxCartPagelet
    {
        return $this->pagelet;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
