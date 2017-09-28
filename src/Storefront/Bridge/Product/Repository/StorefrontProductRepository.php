<?php declare(strict_types=1);

namespace Shopware\Storefront\Bridge\Product\Repository;

use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\PriceDefinition;
use Shopware\Cart\Tax\PercentageTaxRule;
use Shopware\Cart\Tax\TaxRuleCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Product\Repository\ProductRepository;
use Shopware\Product\Searcher\ProductSearchResult;
use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicCollection;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicStruct;
use Shopware\ProductMedia\Repository\ProductMediaRepository;
use Shopware\ProductMedia\Searcher\ProductMediaSearchResult;
use Shopware\ProductMedia\Struct\ProductMediaBasicStruct;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;
use Shopware\Storefront\Bridge\Product\Struct\DetailProductStruct;
use Shopware\Storefront\Bridge\Product\Struct\ListingPriceStruct;
use Shopware\Storefront\Bridge\Product\Struct\ListingProductStruct;
use Shopware\Storefront\Bridge\Product\Struct\ProductBasicStruct;

class StorefrontProductRepository
{
    /**
     * @var ProductRepository
     */
    private $repository;

    /**
     * @var PriceCalculator
     */
    private $priceCalculator;

    /**
     * @var ProductMediaRepository
     */
    private $productMediaRepository;

    public function __construct(
        ProductRepository $repository,
        PriceCalculator $priceCalculator,
        ProductMediaRepository $productMediaRepository
    ) {
        $this->repository = $repository;
        $this->priceCalculator = $priceCalculator;
        $this->productMediaRepository = $productMediaRepository;
    }

    public function readDetail(array $uuids, ShopContext $context): ProductBasicCollection
    {
        $products = $this->repository->read($uuids, $context->getTranslationContext());

        $detailProducts = new ProductBasicCollection();

        foreach ($products as $product) {
            $detailProduct = DetailProductStruct::createFrom($product);

            $prices = $this->filterCustomerPrices($detailProduct->getMainDetail()->getPrices(), $context);
            $detailProduct->getMainDetail()->setPrices(
                $this->calculatePrices($detailProduct, $prices, $context)
            );

            // media
            $cover = $product->getMedia()->filterByProductUuid($product->getUuid())
                ->filter(function (ProductMediaBasicStruct $productMedia) {
                    return $productMedia->getIsCover() === true;
                })
                ->first();

            $detailProduct->setCover($cover);

            $detailProducts->add($detailProduct);
        }

        return $detailProducts;
    }

    public function read(array $uuids, ShopContext $context): ProductBasicCollection
    {
        $products = $this->repository->read($uuids, $context->getTranslationContext());

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_media.product_uuid', $uuids));
        /** @var ProductMediaSearchResult $media */
        $media = $this->productMediaRepository->search($criteria, $context->getTranslationContext());

        $listingProducts = new ProductBasicCollection();

        /** @var ProductBasicStruct $product */
        foreach ($products as $product) {
            $listingProduct = ListingProductStruct::createFrom($product);

            $this->updatePrices($listingProduct, $context);

            // media
            $cover = $media->filterByProductUuid($product->getUuid())
                ->filter(function (ProductMediaBasicStruct $productMedia) {
                    return $productMedia->getIsCover() === true;
                })
                ->first();

            $listingProduct->setCover($cover);

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

    /**
     * @param ProductDetailPriceBasicCollection|ProductListingPriceBasicCollection $prices
     * @param ShopContext                                                          $context
     *
     * @return ProductDetailPriceBasicCollection|ProductListingPriceBasicCollection
     */
    private function filterCustomerPrices($prices, ShopContext $context)
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

    /**
     * @param ProductBasicStruct                                                   $product
     * @param ProductDetailPriceBasicCollection|ProductListingPriceBasicCollection $prices
     * @param ShopContext                                                          $context
     *
     * @return ProductDetailPriceBasicCollection|ProductListingPriceBasicCollection
     */
    private function calculatePrices(ProductBasicStruct $product, $prices, ShopContext $context)
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

    private function updatePrices(ProductBasicStruct $product, ShopContext $context): void
    {
        $prices = $this->filterCustomerPrices($product->getMainDetail()->getPrices(), $context);
        $prices = $this->calculatePrices($product, $prices, $context);
        $product->getMainDetail()->setPrices($prices);

        $prices = $this->filterCustomerPrices($product->getListingPrices(), $context);
        $prices = $this->calculatePrices($product, $prices, $context);
        $product->setListingPrices($prices);
    }
}
