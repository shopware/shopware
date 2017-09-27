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
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\Product\Struct\ProductDetailCollection;
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
        $products = $this->repository->readDetail($uuids, $context->getTranslationContext());

        $detailProducts = new ProductDetailCollection();

        foreach ($products as $product) {
            $detailProduct = DetailProductStruct::createFrom($product);

            // price
            $calculated = $this->getCalculatedPrices($detailProduct, $context);
            $detailProduct->getMainDetail()->setPrices($calculated);

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

            $listingPrice = $this->getListingPrice($listingProduct);
            if (null === $listingPrice) {
                continue;
            }

            // prices
            $listingProduct->getMainDetail()->setPrices(
                $this->getCalculatedPrices($listingProduct, $context)
            );
            $listingProduct->setListingPrice($listingPrice);

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

    private function getCalculatedPrices(ProductBasicStruct $product, ShopContext $context): ProductDetailPriceBasicCollection
    {
        $productPrices = $this->filterCustomerPrices(
            $product->getMainDetail()->getPrices(),
            $context
        );

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

    private function calculatePrices(
        ProductBasicStruct $product,
        ProductDetailPriceBasicCollection $prices,
        ShopContext $context
    ): ProductDetailPriceBasicCollection {
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

    private function getListingPrice(ListingProductStruct $listingProduct): ?ListingPriceStruct
    {
        if ($listingProduct->getMainDetail()->getPrices()->count() === 0) {
            return null;
        }

        $listingPrice = ListingPriceStruct::createFrom(
            $listingProduct->getMainDetail()->getPrices()->last()
        );

        $listingPrice->setHasDifferentPrices(
            $listingProduct->getMainDetail()->getPrices()->count() > 0
        );

        return $listingPrice;
    }
}
