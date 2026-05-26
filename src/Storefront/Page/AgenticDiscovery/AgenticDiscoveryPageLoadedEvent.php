<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AgenticDiscovery;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after an agentic discovery page has been assembled but before
 * it is rendered. Listeners MAY mutate the page (e.g. swap or annotate the
 * embedded manifest) — the page is then handed to Twig as-is.
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 */
#[Package('framework')]
class AgenticDiscoveryPageLoadedEvent extends Event implements ShopwareEvent
{
    public function __construct(
        protected readonly AgenticDiscoveryPage $page,
        protected readonly Context $context,
        protected readonly Request $request,
    ) {
    }

    public function getPage(): AgenticDiscoveryPage
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
