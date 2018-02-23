<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Detail;

use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Bridge\Product\Repository\StorefrontProductRepository;
use Shopware\Storefront\Bridge\Product\Struct\ProductBasicStruct;

class DetailPageLoader
{
    /**
     * @var StorefrontProductRepository
     */
    private $productRepository;

    public function __construct(StorefrontProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function load(string $productId, StorefrontContext $context): ProductBasicStruct
    {
        $collection = $this->productRepository->read([$productId], $context);

        if (!$collection->has($productId)) {
            throw new \RuntimeException('Product was not found.');
        }

        return $collection->get($productId);
    }
}
