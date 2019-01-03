<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Event;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Storefront\Framework\Page\PageletRequest;
use Symfony\Component\HttpFoundation\Request;

class PageletRequestEvent extends NestedEvent
{
    public const NAME = 'pagelet.request.event';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutContext
     */
    private $context;

    /**
     * @var PageletRequest
     */
    private $pageletRequest;

    public function __construct(Request $request, CheckoutContext $context, PageletRequest $pageletRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->pageletRequest = $pageletRequest;
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

    public function getPageletRequest(): PageletRequest
    {
        return $this->pageletRequest;
    }
}
