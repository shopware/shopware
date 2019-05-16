<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Suggest;

use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestGatewayInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class SuggestPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductSuggestGatewayInterface
     */
    private $suggestGateway;

    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ProductSuggestGatewayInterface $suggestGateway,
        GenericPageLoader $genericLoader
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->suggestGateway = $suggestGateway;
        $this->genericLoader = $genericLoader;
    }

    public function load(Request $request, SalesChannelContext $context): SuggestPage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = SuggestPage::createFrom($page);

        $page->setSearchResult(
            $this->suggestGateway->suggest($request, $context)
        );

        $page->setSearchTerm(
            $request->query->get('search')
        );

        $this->eventDispatcher->dispatch(
            SuggestPageLoadedEvent::NAME,
            new SuggestPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
