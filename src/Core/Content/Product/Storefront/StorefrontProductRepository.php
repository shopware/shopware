<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Storefront;

use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class StorefrontProductRepository
{
    public const VISIBILITY_FILTERED = 'product-visibility';

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var QuantityPriceCalculator
     */
    private $priceCalculator;

    public function __construct(EntityRepositoryInterface $repository, QuantityPriceCalculator $priceCalculator)
    {
        $this->productRepository = $repository;
        $this->priceCalculator = $priceCalculator;
    }

    public function read(Criteria $criteria, SalesChannelContext $context): ProductCollection
    {
        $this->addActiveFilters($criteria, $context);
        $this->addProperties($criteria);
        $this->addMedia($criteria);
        $this->addPriceRules($criteria);

        /** @var ProductCollection $basics */
        $basics = $this->productRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        return $this->loadListProducts($basics, $context);
    }

    public function search(Criteria $criteria, SalesChannelContext $context): EntitySearchResult
    {
        $this->addActiveFilters($criteria, $context);

        $basics = $this->productRepository->search($criteria, $context->getContext());

        /** @var ProductCollection $products */
        $products = $basics->getEntities();
        $listProducts = $this->loadListProducts($products, $context);

        $basics->clear();

        foreach ($listProducts as $listProduct) {
            $basics->add($listProduct);
        }

        return $basics;
    }

    public function searchIds(Criteria $criteria, SalesChannelContext $context): IdSearchResult
    {
        $this->addActiveFilters($criteria, $context);

        return $this->productRepository->searchIds($criteria, $context->getContext());
    }

    private function loadListProducts(ProductCollection $products, SalesChannelContext $context): ProductCollection
    {
        $listingProducts = new ProductCollection();

        foreach ($products as $base) {
            /** @var StorefrontProductEntity $product */
            $product = StorefrontProductEntity::createFrom($base);
            $listingProducts->add($product);

            $this->calculatePrices($context, $product);
        }

        return $listingProducts;
    }

    private function calculatePrices(SalesChannelContext $context, StorefrontProductEntity $product): void
    {
        //calculate listing price
        $listingPriceDefinition = $product->getListingPriceDefinition($context->getContext());
        $listingPrice = $this->priceCalculator->calculate($listingPriceDefinition, $context);
        $product->setCalculatedListingPrice($listingPrice);

        //calculate context prices
        $priceRuleDefinitions = $product->getPriceDefinitions($context->getContext());
        $prices = $this->priceCalculator->calculateCollection($priceRuleDefinitions, $context);
        $product->setCalculatedPriceRules($prices);

        //calculate simple price
        $priceDefinition = $product->getPriceDefinition($context->getContext());
        $price = $this->priceCalculator->calculate($priceDefinition, $context);
        $product->setCalculatedPrice($price);
    }

    private function addActiveFilters(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('product.active', true));

        if ($criteria->hasState(self::VISIBILITY_FILTERED)) {
            return;
        }

        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new RangeFilter(
                        'product.visibilities.visibility', [RangeFilter::GTE => ProductVisibilityDefinition::VISIBILITY_LINK]
                    ),
                    new EqualsFilter(
                        'product.visibilities.salesChannelId', $context->getSalesChannel()->getId()
                    ),
                ]
            )
        );
    }

    private function addProperties(Criteria $criteria): void
    {
        $propertiesCriteria = new Criteria();
        $propertiesCriteria->addAssociation('group');

        $criteria->addAssociation('product.properties', $propertiesCriteria);
    }

    private function addMedia(Criteria $criteria): void
    {
        $criteria->addAssociation('media');
    }

    private function addPriceRules(Criteria $criteria): void
    {
        $criteria->addAssociation('prices');
    }
}
