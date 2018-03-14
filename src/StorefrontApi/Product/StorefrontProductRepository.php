<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Product;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Entity\Search\Sorting\FieldSorting;
use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Product\Collection\ProductMediaBasicCollection;
use Shopware\Api\Product\Repository\ProductMediaRepository;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Repository\ProductServiceRepository;
use Shopware\Api\Product\Struct\ProductMediaSearchResult;
use Shopware\Api\Product\Struct\ProductSearchResult;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Context\Struct\StorefrontContext;

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

    /**
     * @var ProductServiceRepository
     */
    private $serviceRepository;

    public function __construct(
        ProductRepository $repository,
        PriceCalculator $priceCalculator,
        ProductMediaRepository $productMediaRepository,
        ProductServiceRepository $serviceRepository
    ) {
        $this->repository = $repository;
        $this->priceCalculator = $priceCalculator;
        $this->productMediaRepository = $productMediaRepository;
        $this->serviceRepository = $serviceRepository;
    }

    public function read(array $ids, StorefrontContext $context): ProductBasicCollection
    {
        $basics = $this->repository->readBasic($ids, $context->getShopContext());

        return $this->loadListProducts($basics, $context);
    }

    public function readDetail(array $ids, StorefrontContext $context): ProductBasicCollection
    {
        $basics = $this->repository->readBasic($ids, $context->getShopContext());

        $products = $this->loadListProducts($basics, $context);

        $collection = $this->loadDetailProducts($ids, $context, $products);

        return $collection;
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
            $listingProducts->add($product);

            //calculate listing price
            $listingPriceDefinition = $product->getListingPriceDefinition($context->getShopContext());
            $listingPrice = $this->priceCalculator->calculate($listingPriceDefinition, $context);
            $product->setCalculatedListingPrice($listingPrice);

            //calculate context prices
            $contextPriceDefinitions = $product->getContextPriceDefinitions($context->getShopContext());
            $contextPrices = $this->priceCalculator->calculateCollection($contextPriceDefinitions, $context);
            $product->setCalculatedContextPrices($contextPrices);

            //calculate simple price
            $priceDefinition = $product->getPriceDefinition($context->getShopContext());
            $price = $this->priceCalculator->calculate($priceDefinition, $context);
            $product->setCalculatedPrice($price);

            $productMedia = $media->filterByProductId($product->getId())->getElements();
            $product->setMedia(new ProductMediaBasicCollection($productMedia));
        }

        return $listingProducts;
    }

    private function loadDetailProducts(array $ids, StorefrontContext $context, ProductBasicCollection $products): ProductBasicCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_service.productId', $ids));

        $services = $this->serviceRepository->search($criteria, $context->getShopContext());

        $collection = new ProductBasicCollection();
        foreach ($products as $product) {
            $detail = ProductDetailStruct::createFrom($product);

            $productServices = $services->filterByProductId($product->getId());

            $detail->setServices($productServices);
            $collection->add($detail);
        }

        return $collection;
    }
}
