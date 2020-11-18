<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.4.0 - Use \Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute instead
 */
class ProductLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AbstractProductDetailRoute
     */
    private $productRoute;

    public function __construct(
        AbstractProductDetailRoute $productRoute,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->productRoute = $productRoute;
    }

    public function load(string $productId, SalesChannelContext $salesChannelContext, ?string $event = null): SalesChannelProductEntity
    {
        $criteria = (new Criteria())
            ->addAssociation('manufacturer.media')
            ->addAssociation('options.group')
            ->addAssociation('properties.group')
            ->addAssociation('mainCategories.category');

        $criteria
            ->getAssociation('media')
            ->addSorting(new FieldSorting('position'));

        $this->eventDispatcher->dispatch(
            new ProductLoaderCriteriaEvent($criteria, $salesChannelContext)
        );

        if ($event) {
            // @deprecated tag:v6.4.0 - `ProductPageCriteriaEvent` or `MinimalQuickViewPageCriteriaEvent` will be dispatched in corresponding page loader classes
            $instance = new $event($productId, $criteria, $salesChannelContext);
            $this->eventDispatcher->dispatch($instance);
        }

        $result = $this->productRoute->load($productId, new Request(), $salesChannelContext, $criteria);

        $result->getProduct()->setConfigurator(
            $result->getConfigurator()
        );

        return $result->getProduct();
    }
}
