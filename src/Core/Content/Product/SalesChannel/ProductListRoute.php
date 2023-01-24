<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package inventory
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
class ProductListRoute extends AbstractProductListRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly SalesChannelRepository $productRepository)
    {
    }

    public function getDecorated(): AbstractProductListRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.2.0")
     */
    #[Route(path: '/store-api/product', name: 'store-api.product.search', methods: ['GET', 'POST'], defaults: ['_entity' => 'product'])]
    public function load(Criteria $criteria, SalesChannelContext $context): ProductListResponse
    {
        return new ProductListResponse($this->productRepository->search($criteria, $context));
    }
}
