<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Navigation;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class NavigationPageletRequestEvent extends NestedEvent
{
    public const NAME = 'navigation.pagelet.request';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $httpRequest;

    /**
     * @var NavigationPageletRequest
     */
    protected $pageletRequest;

    public function __construct(Request $request, CheckoutContext $context, NavigationPageletRequest $pageletRequest)
    {
        $this->context = $context;
        $this->httpRequest = $request;
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

    public function getHttpRequest(): Request
    {
        return $this->httpRequest;
    }

    public function getPageletRequest(): NavigationPageletRequest
    {
        return $this->pageletRequest;
    }
}
