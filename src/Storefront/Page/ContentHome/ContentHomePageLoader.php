<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ContentHome;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletLoader;
use Shopware\Storefront\Pagelet\ContentHome\ContentHomePageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ContentHomePageLoader
{
    /**
     * @var ContentHomePageletLoader
     */
    private $contentHomePageletLoader;

    /**
     * @var ContentHeaderPageletLoader
     */
    private $headerPageletLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        ContentHomePageletLoader $contentHomePageletLoader,
        ContentHeaderPageletLoader $headerPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->contentHomePageletLoader = $contentHomePageletLoader;
        $this->headerPageletLoader = $headerPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param ContentHomePageRequest $request
     * @param CheckoutContext        $context
     *
     * @return ContentHomePageStruct
     */
    public function load(ContentHomePageRequest $request, CheckoutContext $context): ContentHomePageStruct
    {
        $page = new ContentHomePageStruct();
        $page->setContentHome(
            $this->contentHomePageletLoader->load($request->getContentHomeRequest(), $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request->getHeaderRequest(), $context)
        );

        $this->eventDispatcher->dispatch(
            ContentHomePageLoadedEvent::NAME,
            new ContentHomePageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
