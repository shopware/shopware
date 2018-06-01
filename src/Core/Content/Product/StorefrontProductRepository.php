<?php declare(strict_types=1);

namespace Shopware\Content\Product;

use Shopware\Checkout\CustomerContext;
use Shopware\Checkout\Cart\Price\PriceCalculator;
use Shopware\Content\Product\Aggregate\ProductService\Struct\ProductServiceBasicStruct;
use Shopware\Content\Product\Collection\ProductBasicCollection;
use Shopware\Content\Product\Collection\ProductDetailCollection;
use Shopware\Content\Product\Struct\ProductSearchResult;
use Shopware\Content\Product\Struct\StorefrontProductBasicInterface;
use Shopware\Content\Product\Struct\StorefrontProductBasicStruct;
use Shopware\Content\Product\Struct\StorefrontProductDetailStruct;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\IdSearchResult;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\Struct\ConfigurationGroupOptionBasicStruct;

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

    public function __construct(ProductRepository $repository, PriceCalculator $priceCalculator)
    {
        $this->repository = $repository;
        $this->priceCalculator = $priceCalculator;
    }

    public function read(array $ids, CustomerContext $context): ProductBasicCollection
    {
        $basics = $this->repository->readBasic($ids, $context->getApplicationContext());

        return $this->loadListProducts($basics, $context);
    }

    public function readDetail(array $ids, CustomerContext $context): ProductBasicCollection
    {
        $basics = $this->repository->readDetail($ids, $context->getApplicationContext());

        return $this->loadDetailProducts($context, $basics);
    }

    public function search(Criteria $criteria, CustomerContext $context): ProductSearchResult
    {
        $basics = $this->repository->search($criteria, $context->getApplicationContext());

        $listProducts = $this->loadListProducts($basics, $context);

        $basics->clear();
        $basics->fill($listProducts->getElements());

        return $basics;
    }

    public function searchIds(Criteria $criteria, CustomerContext $context): IdSearchResult
    {
        return $this->repository->searchIds($criteria, $context->getApplicationContext());
    }

    private function loadListProducts(ProductBasicCollection $products, CustomerContext $context): ProductBasicCollection
    {
        $listingProducts = new ProductBasicCollection();

        foreach ($products as $base) {
            /** @var StorefrontProductBasicStruct $product */
            $product = StorefrontProductBasicStruct::createFrom($base);
            $listingProducts->add($product);

            $this->calculatePrices($context, $product);
        }

        return $listingProducts;
    }

    private function loadDetailProducts(CustomerContext $context, ProductDetailCollection $products): ProductBasicCollection
    {
        $collection = new ProductBasicCollection();

        foreach ($products as $product) {
            /** @var StorefrontProductDetailStruct $detail */
            $detail = StorefrontProductDetailStruct::createFrom($product);

            $this->calculatePrices($context, $detail);

            $detail->getServices()->sort(function (ProductServiceBasicStruct $a, ProductServiceBasicStruct $b) {
                return $a->getOption()->getGroupId() <=> $b->getOption()->getGroupId();
            });

            $detail->getDatasheet()->sort(function (ConfigurationGroupOptionBasicStruct $a, ConfigurationGroupOptionBasicStruct $b) {
                return $a->getGroupId() <=> $b->getGroupId();
            });

            $collection->add($detail);
        }

        return $collection;
    }

    private function calculatePrices(CustomerContext $context, StorefrontProductBasicInterface $product): void
    {
        //calculate listing price
        $listingPriceDefinition = $product->getListingPriceDefinition($context->getApplicationContext());
        $listingPrice = $this->priceCalculator->calculate($listingPriceDefinition, $context);
        $product->setCalculatedListingPrice($listingPrice);

        //calculate context prices
        $contextPriceDefinitions = $product->getContextPriceDefinitions($context->getApplicationContext());
        $contextPrices = $this->priceCalculator->calculateCollection($contextPriceDefinitions, $context);
        $product->setCalculatedContextPrices($contextPrices);

        //calculate simple price
        $priceDefinition = $product->getPriceDefinition($context->getApplicationContext());
        $price = $this->priceCalculator->calculate($priceDefinition, $context);
        $product->setCalculatedPrice($price);
    }
}
