<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ContentHome;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
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

    public function load(InternalRequest $request, CheckoutContext $context): ContentHomePageStruct
    {
        $page = new ContentHomePageStruct();
        $page->setContentHome(
            $this->contentHomePageletLoader->load($request, $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request, $context)
        );

        $this->eventDispatcher->dispatch(
            ContentHomePageLoadedEvent::NAME,
            new ContentHomePageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
