<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Storefront;

use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionStruct;
use Shopware\Core\Content\Product\Aggregate\ProductService\ProductServiceStruct;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class StorefrontProductRepository
{
    /**
     * @var RepositoryInterface
     */
    private $productRepository;

    /**
     * @var QuantityPriceCalculator
     */
    private $priceCalculator;

    public function __construct(RepositoryInterface $repository, QuantityPriceCalculator $priceCalculator)
    {
        $this->productRepository = $repository;
        $this->priceCalculator = $priceCalculator;
    }

    public function read(array $ids, CheckoutContext $context): ProductCollection
    {
        /** @var ProductCollection $basics */
        $basics = $this->productRepository->read(new ReadCriteria($ids), $context->getContext());

        return $this->loadListProducts($basics, $context);
    }

    public function readDetail(array $ids, CheckoutContext $context): ProductCollection
    {
        $criteria = new ReadCriteria($ids);
        $criteria->addAssociation('product.datasheet');
        $criteria->addAssociation('product.services');

        /** @var ProductCollection $basics */
        $basics = $this->productRepository->read($criteria, $context->getContext());

        return $this->loadDetailProducts($context, $basics);
    }

    public function search(Criteria $criteria, CheckoutContext $context): EntitySearchResult
    {
        $basics = $this->productRepository->search($criteria, $context->getContext());

        /** @var ProductCollection $products */
        $products = $basics->getEntities();
        $listProducts = $this->loadListProducts($products, $context);

        $basics->clear();
        $basics->fill($listProducts->getElements());

        return $basics;
    }

    public function searchIds(Criteria $criteria, CheckoutContext $context): IdSearchResult
    {
        return $this->productRepository->searchIds($criteria, $context->getContext());
    }

    private function loadListProducts(ProductCollection $products, CheckoutContext $context): ProductCollection
    {
        $listingProducts = new ProductCollection();

        foreach ($products as $base) {
            /** @var StorefrontProductStruct $product */
            $product = StorefrontProductStruct::createFrom($base);
            $listingProducts->add($product);

            $this->calculatePrices($context, $product);
        }

        return $listingProducts;
    }

    private function loadDetailProducts(CheckoutContext $context, ProductCollection $products): ProductCollection
    {
        $collection = new ProductCollection();

        foreach ($products as $product) {
            /** @var StorefrontProductStruct $detail */
            $detail = StorefrontProductStruct::createFrom($product);

            $this->calculatePrices($context, $detail);

            if ($detail->getServices()) {
                $detail->getServices()->sort(function (ProductServiceStruct $a, ProductServiceStruct $b) {
                    return $a->getOption()->getGroupId() <=> $b->getOption()->getGroupId();
                });
            }

            if ($detail->getDatasheet()) {
                $detail->getDatasheet()->sort(function (ConfigurationGroupOptionStruct $a, ConfigurationGroupOptionStruct $b) {
                    return $a->getGroupId() <=> $b->getGroupId();
                });
            }

            $collection->add($detail);
        }

        return $collection;
    }

    private function calculatePrices(CheckoutContext $context, StorefrontProductStruct $product): void
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
        $priceDefinition = $product->getPriceDefinition();
        $price = $this->priceCalculator->calculate($priceDefinition, $context);
        $product->setCalculatedPrice($price);
    }
}
