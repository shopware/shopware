<?php declare(strict_types=1);

namespace Shopware\Storefront\Bridge\Product\Repository;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Entity\Search\Sorting\FieldSorting;
use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Product\Repository\ProductMediaRepository;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductMediaSearchResult;
use Shopware\Api\Product\Struct\ProductSearchResult;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\Struct\PriceCollection;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Context\Struct\StorefrontContext;
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

    public function read(array $ids, StorefrontContext $context): ProductBasicCollection
    {
        $basics = $this->repository->readBasic($ids, $context->getShopContext());

        return $this->loadListProducts($basics, $context);
    }

    public function search(Criteria $criteria, StorefrontContext $context): ProductSearchResult
    {
        $basics = $this->repository->search($criteria, $context->getShopContext());
        $listProducts = $this->loadListProducts($basics, $context);

        $basics->clear();
        $basics->fill($listProducts->getElements());

        return $basics;
    }

    private function fetchMedia(array $ids, StorefrontContext $context): ProductMediaSearchResult
    {
        /** @var ProductMediaSearchResult $media */
        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_media.productId', $ids));
        $criteria->addSorting(new FieldSorting('product_media.isCover', FieldSorting::DESCENDING));
        $criteria->addSorting(new FieldSorting('product_media.position'));

        return $this->productMediaRepository->search($criteria, $context->getShopContext());
    }

    private function loadListProducts(ProductBasicCollection $products, StorefrontContext $context): ProductBasicCollection
    {
        $media = $this->fetchMedia($products->getIds(), $context);

        $listingProducts = new ProductBasicCollection();

        foreach ($products as $base) {
            /** @var ProductBasicStruct $product */
            $product = ProductBasicStruct::createFrom($base);

            $price = $product->getListingPriceDefinition($context->getShopContext());

            $product->setCalculatedListingPrice(
                $this->priceCalculator->calculate($price, $context)
            );

            $prices = $product->getPricesDefinition($context->getShopContext());

            $prices = array_map(
                function(PriceDefinition $definition) use ($context) {
                    return $this->priceCalculator->calculate($definition, $context);
                },
                $prices
            );

            $product->setCalculatedPrices(new PriceCollection($prices));

            $product->setMedia(
                $media->filterByProductId($product->getId())
            );

            $listingProducts->add($product);
        }

        return $listingProducts;
    }

}
