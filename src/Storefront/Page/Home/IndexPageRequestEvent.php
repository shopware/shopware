<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Home;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

class IndexPageRequestEvent extends Event
{
    public const NAME = 'content.index.page.request.event';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutContext
     */
    private $context;

    /**
     * @var IndexPageRequest
     */
    private $indexPageRequest;

    public function __construct(Request $request, CheckoutContext $context, IndexPageRequest $indexPageRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->indexPageRequest = $indexPageRequest;
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

    public function getIndexPageRequest(): IndexPageRequest
    {
        return $this->indexPageRequest;
    }
}
