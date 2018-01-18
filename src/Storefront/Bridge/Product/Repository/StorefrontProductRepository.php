<?php declare(strict_types=1);

namespace Shopware\Storefront\Bridge\Product\Repository;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Entity\Search\Sorting\FieldSorting;
use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Product\Collection\ProductListingPriceBasicCollection;
use Shopware\Api\Product\Collection\ProductPriceBasicCollection;
use Shopware\Api\Product\Repository\ProductMediaRepository;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductMediaSearchResult;
use Shopware\Api\Product\Struct\ProductSearchResult;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Context\Struct\ShopContext;
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

    public function read(array $ids, ShopContext $context): ProductBasicCollection
    {
        $basics = $this->repository->readBasic($ids, $context->getTranslationContext());

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

    private function fetchMedia(array $ids, ShopContext $context): ProductMediaSearchResult
    {
        /** @var ProductMediaSearchResult $media */
        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_media.productId', $ids));
        $criteria->addSorting(new FieldSorting('product_media.isCover', FieldSorting::DESCENDING));
        $criteria->addSorting(new FieldSorting('product_media.position'));

        return $this->productMediaRepository->search($criteria, $context->getTranslationContext());
    }

    private function loadListProducts(ProductBasicCollection $products, ShopContext $context): ProductBasicCollection
    {
        $media = $this->fetchMedia($products->getIds(), $context);

        $listingProducts = new ProductBasicCollection();

        foreach ($products as $base) {
            /** @var ProductBasicStruct $product */
            $product = ProductBasicStruct::createFrom($base);

            $taxRules = new TaxRuleCollection([
                new PercentageTaxRule($product->getTax()->getRate(), 100),
            ]);

            $definition = new PriceDefinition($product->getPrice(), $taxRules, 1, true);
            $product->setCalculatedPrice(
                $this->priceCalculator->calculate($definition, $context)
            );

            $product->setMedia(
                $media->filterByProductId($product->getId())
            );

            $listingProducts->add($product);
        }

        return $listingProducts;
    }
}
