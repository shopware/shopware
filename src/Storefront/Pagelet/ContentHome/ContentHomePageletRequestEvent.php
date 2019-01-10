<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentHome;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class ContentHomePageletRequestEvent extends NestedEvent
{
    public const NAME = 'content-home.pagelet.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var ContentHomePageletRequest
     */
    protected $contentHomePageletRequest;

    public function __construct(Request $request, CheckoutContext $context, ContentHomePageletRequest $contentHomePageletRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->contentHomePageletRequest = $contentHomePageletRequest;
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

    public function getContentHomePageletRequest(): ContentHomePageletRequest
    {
        return $this->contentHomePageletRequest;
    }
}
