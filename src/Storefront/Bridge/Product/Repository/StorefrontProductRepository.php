<?php

namespace Shopware\Storefront\Bridge\Product\Repository;

use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\PriceDefinition;
use Shopware\Cart\Tax\PercentageTaxRule;
use Shopware\Cart\Tax\TaxRuleCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Product\Repository\ProductRepository;
use Shopware\Product\Searcher\ProductSearchResult;
use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\ProductDetailPrice\Repository\ProductDetailPriceRepository;
use Shopware\ProductDetailPrice\Searcher\ProductDetailPriceSearchResult;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicCollection;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicStruct;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;
use Shopware\Storefront\Bridge\Product\Struct\DetailProductStruct;
use Shopware\Storefront\Bridge\Product\Struct\ListingPriceStruct;
use Shopware\Storefront\Bridge\Product\Struct\ListingProductStruct;

class StorefrontProductRepository
{
    /**
     * @var ProductRepository
     */
    private $repository;

    /**
     * @var ProductDetailPriceRepository
     */
    private $priceRepository;

    /**
     * @var PriceCalculator
     */
    private $priceCalculator;

    public function __construct(
        ProductRepository $repository,
        ProductDetailPriceRepository $priceRepository,
        PriceCalculator $priceCalculator
    ) {
        $this->repository = $repository;
        $this->priceRepository = $priceRepository;
        $this->priceCalculator = $priceCalculator;
    }

    public function readDetail(array $uuids, ShopContext $context): ProductBasicCollection
    {
        $products = $this->repository->read($uuids, $context->getTranslationContext());

        $prices = $this->fetchPrices($context, $products);

        $detailProducts = new ProductBasicCollection();

        foreach ($products as $product) {
            $detailProduct = DetailProductStruct::createFrom($product);

            $calculated = $this->getCalculatedPrices($detailProduct, $prices, $context);

            $detailProduct->setPrices($calculated);

            $detailProducts->add($detailProduct);
        }

        return $detailProducts;
    }

    public function read(array $uuids, ShopContext $context): ProductBasicCollection
    {
        $products = $this->repository->read($uuids, $context->getTranslationContext());

        $prices = $this->fetchPrices($context, $products);

        $listingProducts = new ProductBasicCollection();

        foreach ($products as $product) {
            $listingProduct = ListingProductStruct::createFrom($product);

            $listingProduct->setPrices(
                $this->getCalculatedPrices($listingProduct, $prices, $context)
            );
            $listingProduct->setListingPrice($this->getListingPrice($listingProduct));

            $listingProducts->add($listingProduct);
        }

        return $listingProducts;
    }

    public function search(Criteria $criteria, ShopContext $context): ProductSearchResult
    {
        $uuids = $this->repository->searchUuids($criteria, $context->getTranslationContext());

        $products = $this->read($uuids->getUuids(), $context);

        $result = new ProductSearchResult($products->getElements());
        $result->setTotal($uuids->getTotal());

        return $result;
    }

    private function fetchPrices(ShopContext $context, ProductBasicCollection $products): ProductDetailPriceSearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_detail_price.product_detail_uuid', $products->getMainDetailUuids()));
        $criteria->addFilter(new TermsQuery('product_detail_price.customer_group_uuid', [
            $context->getCurrentCustomerGroup()->getUuid(),
            $context->getFallbackCustomerGroup()->getUuid(),
        ]));

        return $this->priceRepository->search($criteria, $context->getTranslationContext());
    }

    private function getCalculatedPrices(ProductBasicStruct $product, ProductDetailPriceSearchResult $prices, ShopContext $context): ProductDetailPriceBasicCollection
    {
        $productPrices = $prices->filterByProductDetailUuid($product->getMainDetailUuid());
        $productPrices = $this->filterCustomerPrices($productPrices, $context);

        return $this->calculatePrices($product, $productPrices, $context);
    }

    private function filterCustomerPrices(ProductDetailPriceBasicCollection $prices, ShopContext $context): ProductDetailPriceBasicCollection
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

    private function calculatePrices(ProductBasicStruct $product, ProductDetailPriceBasicCollection $prices, ShopContext $context): ProductDetailPriceBasicCollection
    {
        $taxRules = new TaxRuleCollection([
            new PercentageTaxRule($product->getTax()->getRate(), 100),
        ]);

        /** @var ProductDetailPriceBasicStruct $price */
        foreach ($prices as $price) {
            $definition = new PriceDefinition($price->getPrice(), $taxRules);
            $calculated = $this->priceCalculator->calculate($definition, $context);
            $price->setPrice($calculated->getTotalPrice());
        }

        return $prices;
    }

    private function getListingPrice(ListingProductStruct $listingProduct): ListingPriceStruct
    {
        $listingPrice = ListingPriceStruct::createFrom(
            $listingProduct->getPrices()->last()
        );

        $listingPrice->setHasDifferentPrices(
            $listingProduct->getPrices()->count() > 0
        );

        return $listingPrice;
    }
}
