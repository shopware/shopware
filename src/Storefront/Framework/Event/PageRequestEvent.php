<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Event;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Storefront\Framework\Page\PageRequest;
use Symfony\Component\HttpFoundation\Request;

class PageRequestEvent extends NestedEvent
{
    public const NAME = 'page.request';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutContext
     */
    private $context;

    /**
     * @var PageRequest
     */
    private $pageRequest;

    public function __construct(Request $request, CheckoutContext $context, PageRequest $pageRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->pageRequest = $pageRequest;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->context;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getPageRequest(): PageRequest
    {
        return $this->pageRequest;
    }
}
