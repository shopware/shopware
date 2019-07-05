<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Suggest;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
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

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): SuggestPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = SuggestPage::createFrom($page);

        $page->setSearchResult($this->suggestGateway->suggest($request, $salesChannelContext));

        $page->setSearchTerm($request->query->get('search'));

        $this->eventDispatcher->dispatch(
            new SuggestPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }
}
