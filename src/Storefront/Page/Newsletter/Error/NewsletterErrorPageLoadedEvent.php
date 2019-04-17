<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Newsletter\Error;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NewsletterErrorPageLoadedEvent extends NestedEvent
{
    public const NAME = 'newsletter-receiver-error.page.loaded';

    /**
     * @var NewsletterErrorPage
     */
    protected $page;

    /**
     * @var SalesChannelContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(NewsletterErrorPage $page, SalesChannelContext $context, Request $request)
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

    public function getPage(): NewsletterErrorPage
    {
        return $this->page;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
