<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Newsletter\Confirm;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NewsletterConfirmPageLoadedEvent extends NestedEvent
{
    public const NAME = 'newsletter-receiver-confirm.page.loaded';

    /**
     * @var NewsletterConfirmPage
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

    public function __construct(NewsletterConfirmPage $page, SalesChannelContext $context, Request $request)
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

    public function getPage(): NewsletterConfirmPage
    {
        return $this->page;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
