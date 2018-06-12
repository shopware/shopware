<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Storefront;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Cart\Price\PriceCalculator;
use Shopware\Core\Content\Product\Aggregate\ProductService\ProductServiceBasicStruct;
use Shopware\Core\Content\Product\ProductBasicCollection;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionBasicStruct;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
use Shopware\Core\Framework\ORM\Search\IdSearchResult;

class StorefrontProductRepository
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var PriceCalculator
     */
    private $priceCalculator;

    public function __construct(RepositoryInterface $repository, PriceCalculator $priceCalculator)
    {
        $this->repository = $repository;
        $this->priceCalculator = $priceCalculator;
    }

    public function read(array $ids, CheckoutContext $context): ProductBasicCollection
    {
        $basics = $this->repository->read($ids, $context->getContext());

        return $this->loadListProducts($basics, $context);
    }

    public function readDetail(array $ids, CheckoutContext $context): ProductBasicCollection
    {
        $basics = $this->repository->read($ids, $context->getContext());

        return $this->loadDetailProducts($context, $basics);
    }

    public function search(Criteria $criteria, CheckoutContext $context): EntitySearchResult
    {
        $basics = $this->repository->search($criteria, $context->getContext());

        $listProducts = $this->loadListProducts($basics->getEntities(), $context);

        $basics->clear();
        $basics->fill($listProducts->getElements());

        return $basics;
    }

    public function searchIds(Criteria $criteria, CheckoutContext $context): IdSearchResult
    {
        return $this->repository->searchIds($criteria, $context->getContext());
    }

    private function loadListProducts(ProductBasicCollection $products, CheckoutContext $context): ProductBasicCollection
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

    private function loadDetailProducts(CheckoutContext $context, ProductDetailCollection $products): ProductBasicCollection
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

    private function calculatePrices(CheckoutContext $context, StorefrontProductBasicStruct $product): void
    {
        //calculate listing price
        $listingPriceDefinition = $product->getListingPriceDefinition($context->getContext());
        $listingPrice = $this->priceCalculator->calculate($listingPriceDefinition, $context);
        $product->setCalculatedListingPrice($listingPrice);

        //calculate context prices
        $priceRuleDefinitions = $product->getPriceRuleDefinitions($context->getContext());
        $priceRules = $this->priceCalculator->calculateCollection($priceRuleDefinitions, $context);
        $product->setCalculatedPriceRules($priceRules);

        //calculate simple price
        $priceDefinition = $product->getPriceDefinition($context->getContext());
        $price = $this->priceCalculator->calculate($priceDefinition, $context);
        $product->setCalculatedPrice($price);
    }
}
