<?php

namespace Shopware\Product\Repository;

use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\PriceDefinition;
use Shopware\Cart\Tax\PercentageTaxRule;
use Shopware\Cart\Tax\TaxRuleCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Product\Searcher\ProductSearchResult;
use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\Product\Struct\StoreFrontListingProductStruct;
use Shopware\ProductPrice\Repository\ProductPriceRepository;
use Shopware\ProductPrice\Searcher\ProductPriceSearchResult;
use Shopware\ProductPrice\Struct\ProductListingPrice;
use Shopware\ProductPrice\Struct\ProductPriceBasicCollection;
use Shopware\ProductPrice\Struct\ProductPriceBasicStruct;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class StorefrontProductRepository
{
    /**
     * @var ProductRepository
     */
    private $repository;

    /**
     * @var ProductPriceRepository
     */
    private $priceRepository;

    /**
     * @var PriceCalculator
     */
    private $priceCalculator;

    public function __construct(
        ProductRepository $repository,
        ProductPriceRepository $priceRepository,
        PriceCalculator $priceCalculator
    ) {
        $this->repository = $repository;
        $this->priceRepository = $priceRepository;
        $this->priceCalculator = $priceCalculator;
    }

    public function search(Criteria $criteria, ShopContext $context): ProductSearchResult
    {
        $uuids = $this->repository->searchUuids($criteria, $context->getTranslationContext());

        $products = $this->repository->read($uuids->getUuids(), $context->getTranslationContext());

        $prices = $this->fetchPrices($context, $products);

        $listingProducts = new ProductBasicCollection();

        foreach ($products as $product) {
            $listingProduct = StoreFrontListingProductStruct::createFrom($product);

            $productPrices = $prices->filterByProductDetailUuid($listingProduct->getMainDetailUuid());
            $productPrices = $this->filterCustomerPrices($productPrices, $context);
            $calcualted = $this->calculatePrices($listingProduct, $productPrices, $context);

            $listingProduct->setPrices($calcualted);
            $listingProduct->setListingPrice($this->getListingPrice($listingProduct, $context));

            $listingProducts->add($listingProduct);
        }

        $result = new ProductSearchResult($listingProducts->getElements());
        $result->setTotal($uuids->getTotal());

        return $result;
    }

    private function filterCustomerPrices(
        ProductPriceBasicCollection $prices,
        ShopContext $context
    ): ProductPriceBasicCollection
    {
        $current = $prices->filterByCustomerGroupUuid(
            $context->getCurrentCustomerGroup()->getUuid()
        );
        if ($current->count() > 0) {
            return $current;
        }

        return $prices->filterByCustomerGroupUuid(
            $context->getFallbackCustomerGroup()->getUuid()
        );
    }

    private function calculatePrices(
        ProductBasicStruct $product,
        ProductPriceBasicCollection $prices,
        ShopContext $context
    ): ProductPriceBasicCollection
    {
        $taxRules = new TaxRuleCollection([
            new PercentageTaxRule($product->getTax()->getRate(), 100)
        ]);

        /** @var ProductPriceBasicStruct $price */
        foreach ($prices as $price) {
            $definition = new PriceDefinition($price->getPrice(), $taxRules);
            $calculated = $this->priceCalculator->calculate($definition, $context);
            $price->setPrice($calculated->getTotalPrice());
        }

        return $prices;
    }

    private function getListingPrice(StoreFrontListingProductStruct $listingProduct, ShopContext $context): ProductListingPrice
    {
        $listingPrice = ProductListingPrice::createFrom(
            $listingProduct->getPrices()->first()
        );

        $listingPrice->setHasDifferentPrices(
            $listingProduct->getPrices()->count() > 0
        );

        return $listingPrice;
    }

    public function fetchPrices(
        ShopContext $context,
        ProductBasicCollection $products
    ): ProductPriceSearchResult {

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_price.product_detail_uuid', $products->getMainDetailUuids()));
        $criteria->addFilter(new TermsQuery('product_price.customer_group_uuid', [
            $context->getCurrentCustomerGroup()->getUuid(),
            $context->getFallbackCustomerGroup()->getUuid()
        ]));

        return $this->priceRepository->search($criteria, $context->getTranslationContext());
    }
}