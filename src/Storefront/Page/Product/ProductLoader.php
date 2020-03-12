<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductLoader
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        SalesChannelRepositoryInterface $productRepository,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfigService
    ) {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws ProductNotFoundException
     */
    public function load(string $productId, SalesChannelContext $salesChannelContext): SalesChannelProductEntity
    {
        $criteria = (new Criteria([$productId]))
            ->addFilter(new ProductAvailableFilter($salesChannelContext->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_LINK))
            ->addAssociation('media')
            ->addAssociation('prices')
            ->addAssociation('manufacturer')
            ->addAssociation('manufacturer.media')
            ->addAssociation('cover')
            ->addAssociation('properties.group')
            ->addAssociation('mainCategories.category');

        $criteria->getAssociation('media')->addSorting(new FieldSorting('position'));

        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $hideCloseoutProductsWhenOutOfStock = $this->systemConfigService->get('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelId);

        if ($hideCloseoutProductsWhenOutOfStock) {
            $criteria->addFilter(
                new NotFilter(
                    NotFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('product.isCloseout', true),
                        new EqualsFilter('product.available', false),
                        new EqualsFilter('product.parentId', null),
                    ]
                )
            );
        }

        $this->eventDispatcher->dispatch(
            new ProductLoaderCriteriaEvent($criteria, $salesChannelContext)
        );

        /** @var SalesChannelProductEntity|null $product */
        $product = $this->productRepository->search($criteria, $salesChannelContext)->get($productId);

        if (!$product) {
            throw new ProductNotFoundException($productId);
        }

        $product->setSortedProperties(
            $this->sortProperties($product)
        );

        return $product;
    }

    private function sortProperties(SalesChannelProductEntity $product): PropertyGroupCollection
    {
        $properties = $product->getProperties();
        if ($properties === null) {
            return new PropertyGroupCollection();
        }

        $sorted = [];
        foreach ($properties as $option) {
            $group = $option->getGroup();

            if (!$group) {
                continue;
            }

            if (!$group->getOptions()) {
                $group->setOptions(new PropertyGroupOptionCollection());
            }

            $group->getOptions()->add($option);

            $sorted[$group->getId()] = $group;
        }

        usort(
            $sorted,
            static function (PropertyGroupEntity $a, PropertyGroupEntity $b) {
                return strnatcmp($a->getTranslation('name'), $b->getTranslation('name'));
            }
        );

        foreach ($sorted as $group) {
            $group->getOptions()->sort(
                static function (PropertyGroupOptionEntity $a, PropertyGroupOptionEntity $b) use ($group) {
                    if ($group->getSortingType() === PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC) {
                        return strnatcmp($a->getTranslation('name'), $b->getTranslation('name'));
                    }

                    if ($group->getSortingType() === PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC) {
                        return $a->getTranslation('name') <=> $b->getTranslation('name');
                    }

                    return $a->getPosition() <=> $b->getPosition();
                }
            );
        }

        return new PropertyGroupCollection($sorted);
    }
}
