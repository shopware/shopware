<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Suggest;

use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestGatewayInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class SuggestPageletLoader implements PageLoaderInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var \Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestGatewayInterface
     */
    private $suggestGateway;

    public function __construct(EventDispatcherInterface $eventDispatcher, ProductSuggestGatewayInterface $suggestGateway)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->suggestGateway = $suggestGateway;
    }

    public function load(Request $request, SalesChannelContext $context): SuggestPagelet
    {
        $page = new SuggestPagelet(
            $this->suggestGateway->suggest($request, $context),
            $request->query->get('search')
        );

        $this->eventDispatcher->dispatch(
            SuggestPageletLoadedEvent::NAME,
            new SuggestPageletLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
