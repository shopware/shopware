<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Storefront;

use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class StorefrontProductRepository
{
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

    public function read(Criteria $criteria, CheckoutContext $context): ProductCollection
    {
        /** @var ProductCollection $basics */
        $basics = $this->productRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        return $this->loadListProducts($basics, $context);
    }

    public function search(Criteria $criteria, CheckoutContext $context): EntitySearchResult
    {
        $criteria->addFilter(new EqualsFilter('product.active', true));

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

    public function searchIds(Criteria $criteria, CheckoutContext $context): IdSearchResult
    {
        return $this->productRepository->searchIds($criteria, $context->getContext());
    }

    private function loadListProducts(ProductCollection $products, CheckoutContext $context): ProductCollection
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

    private function calculatePrices(CheckoutContext $context, StorefrontProductEntity $product): void
    {
        //calculate listing price
        $listingPriceDefinition = $product->getListingPriceDefinition($context->getContext());
        $listingPrice = $this->priceCalculator->calculate($listingPriceDefinition, $context);
        $product->setCalculatedListingPrice($listingPrice);

        //calculate context prices
        $priceRuleDefinitions = $product->getPriceRuleDefinitions($context->getContext());
        $priceRules = $this->priceCalculator->calculateCollection($priceRuleDefinitions, $context);
        $product->setCalculatedPriceRules($priceRules);

        //calculate simple price
        $priceDefinition = $product->getPriceDefinition();
        $price = $this->priceCalculator->calculate($priceDefinition, $context);
        $product->setCalculatedPrice($price);
    }
}
