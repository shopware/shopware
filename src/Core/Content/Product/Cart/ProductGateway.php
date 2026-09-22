<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Content\Product\Events\ProductGatewayCriteriaEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class ProductGateway implements ProductGatewayInterface
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $repository
     */
    public function __construct(
        private readonly SalesChannelRepository $repository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @param list<string> $ids
     */
    public function get(array $ids, SalesChannelContext $context): ProductCollection
    {
        $criteria = new Criteria($ids);
        $criteria->setTitle('cart::products');
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('featureSet');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('manufacturer');

        // The category path of a line item is resolved from these two associations, see
        // ProductCategoryPathResolver. A cart is recalculated on almost every storefront request,
        // so both are narrowed to the rows the resolver can use at all: a category that is inactive
        // or hidden is never part of a path, and a main category of another sales channel is never
        // selected.
        $criteria->addAssociation('categories');
        $criteria->getAssociation('categories')
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('visible', true));

        $criteria->addAssociation('mainCategories.category');
        $criteria->getAssociation('mainCategories')
            ->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()));

        $this->eventDispatcher->dispatch(
            new ProductGatewayCriteriaEvent($ids, $criteria, $context)
        );

        return $this->repository->search($criteria, $context)->getEntities();
    }
}
