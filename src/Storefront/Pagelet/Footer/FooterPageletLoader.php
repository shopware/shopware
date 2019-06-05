<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Footer;

use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class FooterPageletLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var NavigationLoader
     */
    private $navigationLoader;

    public function __construct(
        NavigationLoader $navigationLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->navigationLoader = $navigationLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(Request $request, SalesChannelContext $context): FooterPagelet
    {
        $footerId = $context->getSalesChannel()->getFooterCategoryId();

        $tree = null;
        if ($footerId) {
            $tree = $this->navigationLoader->load($footerId, $context, $footerId);
        }

        $page = new FooterPagelet($tree);

        $this->eventDispatcher->dispatch(
            new FooterPageletLoadedEvent($page, $context, $request),
            FooterPageletLoadedEvent::NAME
        );

        return $page;
    }
}
