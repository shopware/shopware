<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Detail;

use Shopware\Context\Struct\ShopContext;
use Shopware\Product\Repository\StorefrontProductRepository;
use Shopware\Product\Struct\ProductBasicStruct;

class DetailPageLoader
{
    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    public function __construct(
        StorefrontProductRepository $productRepository
    ) {
        $this->productRepository = $productRepository;
    }

    public function load(
        string $productUuid,
        ShopContext $context
    ): ProductBasicStruct
    {
        $collection = $this->productRepository->readDetail([$productUuid], $context);

        return $collection->get($productUuid);
    }
}