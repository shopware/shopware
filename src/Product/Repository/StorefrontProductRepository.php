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
use Shopware\Product\Struct\ProductDetailCollection;
use Shopware\Product\Struct\StorefrontDetailProductStruct;
use Shopware\Product\Struct\StorefrontListingProductStruct;
use Shopware\ProductPrice\Repository\ProductPriceRepository;
use Shopware\ProductPrice\Searcher\ProductPriceSearchResult;
use Shopware\ProductPrice\Struct\ProductDetailPrice;
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

    public function readDetail(array $uuids, ShopContext $context): ProductDetailCollection
    {
        $products = $this->repository->readDetail($uuids, $context->getTranslationContext());

        $prices = $this->fetchPrices($context, $products);

        $detailProducts = new ProductDetailCollection();

        foreach ($products as $product) {
            $detailProduct = StorefrontDetailProductStruct::createFrom($product);

            $calculated = $this->getCalculatedPrices($detailProduct, $prices, $context);

            $detailProduct->setPrices($calculated);
            $detailProduct->setDetailPrice($this->getDetailPrice($detailProduct));

            $detailProducts->add($detailProduct);
        }

        return $detailProducts;
    }

    public function search(Criteria $criteria, ShopContext $context): ProductSearchResult
    {
        $uuids = $this->repository->searchUuids($criteria, $context->getTranslationContext());

        $products = $this->repository->read($uuids->getUuids(), $context->getTranslationContext());

        $prices = $this->fetchPrices($context, $products);

        $listingProducts = new ProductBasicCollection();

        foreach ($products as $product) {
            $listingProduct = StorefrontListingProductStruct::createFrom($product);

            $calculated = $this->getCalculatedPrices($listingProduct, $prices, $context);

            $listingProduct->setPrices($calculated);
            $listingProduct->setListingPrice($this->getListingPrice($listingProduct));

            $listingProducts->add($listingProduct);
        }

        $result = new ProductSearchResult($listingProducts->getElements());
        $result->setTotal($uuids->getTotal());

        return $result;
    }

    public function fetchPrices(
        ShopContext $context,
        ProductBasicCollection $products
    ): ProductPriceSearchResult {
        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_price.product_detail_uuid', $products->getMainDetailUuids()));
        $criteria->addFilter(new TermsQuery('product_price.customer_group_uuid', [
            $context->getCurrentCustomerGroup()->getUuid(),
            $context->getFallbackCustomerGroup()->getUuid(),
        ]));

        return $this->priceRepository->search($criteria, $context->getTranslationContext());
    }

    private function getCalculatedPrices(
        ProductBasicStruct $product,
        ProductPriceSearchResult $prices,
        ShopContext $context
    ): ProductPriceBasicCollection {
        $productPrices = $prices->filterByProductDetailUuid($product->getMainDetailUuid());
        $productPrices = $this->filterCustomerPrices($productPrices, $context);

        return $this->calculatePrices($product, $productPrices, $context);
    }

    private function filterCustomerPrices(
        ProductPriceBasicCollection $prices,
        ShopContext $context
    ): ProductPriceBasicCollection {
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
    ): ProductPriceBasicCollection {
        $taxRules = new TaxRuleCollection([
            new PercentageTaxRule($product->getTax()->getRate(), 100),
        ]);

        /** @var ProductPriceBasicStruct $price */
        foreach ($prices as $price) {
            $definition = new PriceDefinition($price->getPrice(), $taxRules);
            $calculated = $this->priceCalculator->calculate($definition, $context);
            $price->setPrice($calculated->getTotalPrice());
        }

        return $prices;
    }

    private function getListingPrice(StorefrontListingProductStruct $listingProduct): ProductListingPrice
    {
        $listingPrice = ProductListingPrice::createFrom(
            $listingProduct->getPrices()->first()
        );

        $listingPrice->setHasDifferentPrices(
            $listingProduct->getPrices()->count() > 0
        );

        return $listingPrice;
    }

    private function getDetailPrice(StorefrontDetailProductStruct $detailProduct): ProductDetailPrice
    {
        $detailPrice = ProductDetailPrice::createFrom(
            $detailProduct->getPrices()->first()
        );

        $detailPrice->setHasDifferentPrices(
            $detailProduct->getPrices()->count() > 0
        );

        return $detailPrice;
    }
}
