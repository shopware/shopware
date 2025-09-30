<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class RobotsPageLoadedEvent extends NestedEvent implements ShopwareEvent
{
    public function __construct(
        private readonly RobotsPage $page,
        private readonly Context $context,
        private readonly Request $request,
    ) {
    }

    public function getPage(): RobotsPage
    {
        return $this->page;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
