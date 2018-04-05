<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Product;

use Shopware\Api\Configuration\Struct\ConfigurationGroupOptionBasicStruct;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Entity\Search\Sorting\FieldSorting;
use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Product\Collection\ProductDetailCollection;
use Shopware\Api\Product\Collection\ProductMediaBasicCollection;
use Shopware\Api\Product\Repository\ProductMediaRepository;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductMediaSearchResult;
use Shopware\Api\Product\Struct\ProductSearchResult;
use Shopware\Api\Product\Struct\ProductServiceBasicStruct;
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

    public function readDetail(array $ids, StorefrontContext $context): ProductBasicCollection
    {
        $basics = $this->repository->readDetail($ids, $context->getShopContext());

        return $this->loadDetailProducts($context, $basics);
    }

    public function search(Criteria $criteria, StorefrontContext $context): ProductSearchResult
    {
        $basics = $this->repository->search($criteria, $context->getShopContext());

        $listProducts = $this->loadListProducts($basics, $context);

        $basics->clear();
        $basics->fill($listProducts->getElements());

        return $basics;
    }

    public function searchIds(Criteria $criteria, StorefrontContext $context): IdSearchResult
    {
        return $this->repository->searchIds($criteria, $context->getShopContext());
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

            $this->calculatePrices($context, $product);

            $productMedia = $media->filterByProductId($product->getId())->getElements();
            $product->setMedia(new ProductMediaBasicCollection($productMedia));
        }

        return $listingProducts;
    }

    private function loadDetailProducts(StorefrontContext $context, ProductDetailCollection $products): ProductBasicCollection
    {
        $collection = new ProductBasicCollection();

        foreach ($products as $product) {
            /** @var ProductDetailStruct $detail */
            $detail = ProductDetailStruct::createFrom($product);

            $this->calculatePrices($context, $detail);

            $detail->getServices()->sort(function(ProductServiceBasicStruct $a, ProductServiceBasicStruct $b) {
                return $a->getOption()->getGroupId() <=> $b->getOption()->getGroupId();
            });

            $detail->getDatasheet()->sort(function(ConfigurationGroupOptionBasicStruct $a, ConfigurationGroupOptionBasicStruct $b) {
                return $a->getGroupId() <=> $b->getGroupId();
            });

            $collection->add($detail);
        }

        return $collection;
    }

    private function calculatePrices(StorefrontContext $context, StorefrontProductBasicInterface $product): void
    {
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
    }
}
