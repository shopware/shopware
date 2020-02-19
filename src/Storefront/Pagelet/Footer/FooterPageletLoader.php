<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Footer;

use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Decoratable()
 */
class FooterPageletLoader implements FooterPageletLoaderInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var NavigationLoaderInterface
     */
    private $navigationLoader;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        NavigationLoaderInterface $navigationLoader
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->navigationLoader = $navigationLoader;
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext): FooterPagelet
    {
        $footerId = $salesChannelContext->getSalesChannel()->getFooterCategoryId();

        $tree = null;
        if ($footerId) {
            $navigationId = $request->get('navigationId', $footerId);

            $tree = $this->navigationLoader->load($navigationId, $salesChannelContext, $footerId);
        }

        $pagelet = new FooterPagelet($tree);

        $this->eventDispatcher->dispatch(
            new FooterPageletLoadedEvent($pagelet, $salesChannelContext, $request)
        );

        return $pagelet;
    }
}
