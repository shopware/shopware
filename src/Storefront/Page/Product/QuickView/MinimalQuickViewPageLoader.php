<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\QuickView;

use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class MinimalQuickViewPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AbstractProductDetailRoute
     */
    private $productRoute;

    public function __construct(EventDispatcherInterface $eventDispatcher, AbstractProductDetailRoute $productRoute)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->productRoute = $productRoute;
    }

    /**
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): MinimalQuickViewPage
    {
        $productId = $request->get('productId');
        if (!$productId) {
            throw new MissingRequestParameterException('productId', '/productId');
        }

        $criteria = (new Criteria())
            ->addAssociation('manufacturer.media')
            ->addAssociation('options.group')
            ->addAssociation('properties.group')
            ->addAssociation('mainCategories.category');

        $criteria
            ->getAssociation('media')
            ->addSorting(new FieldSorting('position'));

        $result = $this->productRoute->load($productId, new Request(), $salesChannelContext, $criteria);
        $product = $result->getProduct();

        $page = new MinimalQuickViewPage($product);

        $event = new MinimalQuickViewPageLoadedEvent($page, $salesChannelContext, $request);

        $this->eventDispatcher->dispatch($event);

        return $page;
    }
}
