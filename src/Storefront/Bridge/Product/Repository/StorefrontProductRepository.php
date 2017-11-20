<?php declare(strict_types=1);

namespace Shopware\Storefront\Bridge\Product\Repository;

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\TermsQuery;
use Shopware\Api\Search\Sorting\FieldSorting;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Product\Collection\ProductBasicCollection;
use Shopware\Product\Collection\ProductListingPriceBasicCollection;
use Shopware\Product\Collection\ProductPriceBasicCollection;
use Shopware\Product\Repository\ProductMediaRepository;
use Shopware\Product\Repository\ProductRepository;
use Shopware\Product\Struct\ProductMediaSearchResult;
use Shopware\Product\Struct\ProductSearchResult;
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

    public function read(array $uuids, ShopContext $context): ProductBasicCollection
    {
        $basics = $this->repository->readBasic($uuids, $context->getTranslationContext());

        return $this->loadListProducts($basics, $context);
    }

    public function search(Criteria $criteria, ShopContext $context): ProductSearchResult
    {
        $basics = $this->repository->search($criteria, $context->getTranslationContext());

        $listProducts = $this->loadListProducts($basics, $context);

        $basics->clear();
        $basics->fill($listProducts->getElements());

        return $basics;
    }

    private function fetchMedia(array $uuids, ShopContext $context): ProductMediaSearchResult
    {
        /** @var ProductMediaSearchResult $media */
        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_media.productUuid', $uuids));
        $criteria->addSorting(new FieldSorting('product_media.isCover', FieldSorting::DESCENDING));
        $criteria->addSorting(new FieldSorting('product_media.position'));

        return $this->productMediaRepository->search($criteria, $context->getTranslationContext());
    }

    /**
     * @param ProductPriceBasicCollection|ProductListingPriceBasicCollection $prices
     * @param ShopContext                                                    $context
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
     * @param TaxRuleCollection                                              $taxRules
     * @param ProductPriceBasicCollection|ProductListingPriceBasicCollection $prices
     * @param ShopContext                                                    $context
     *
     * @return ProductListingPriceBasicCollection|ProductPriceBasicCollection
     */
    private function calculatePrices(TaxRuleCollection $taxRules, $prices, ShopContext $context)
    {
        foreach ($prices as $price) {
            $calculated = $this->priceCalculator->calculate(
                new PriceDefinition($price->getPrice(), $taxRules),
                $context
            );

            $price->setPrice($calculated->getTotalPrice());
        }

        return $prices;
    }

    private function loadListProducts(ProductBasicCollection $products, ShopContext $context): ProductBasicCollection
    {
        $media = $this->fetchMedia($products->getUuids(), $context);

        $listingProducts = new ProductBasicCollection();

        foreach ($products as $base) {
            /** @var ProductBasicStruct $product */
            $product = ProductBasicStruct::createFrom($base);

            $taxRules = new TaxRuleCollection([
                new PercentageTaxRule($product->getTax()->getRate(), 100),
            ]);

            $product->setPrices(
                $this->calculatePrices(
                    $taxRules,
                    $this->filterCustomerPrices($product->getPrices(), $context),
                    $context
                )
            );
            $product->setListingPrices(
                $this->calculatePrices(
                    $taxRules,
                    $this->filterCustomerPrices($product->getListingPrices(), $context),
                    $context
                )
            );

            $product->setMedia(
                $media->filterByProductUuid($product->getUuid())
            );

            $listingProducts->add($product);
        }

        return $listingProducts;
    }
}
