<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Api;

use Shopware\Core\Content\Product\Util\VariantCombinationLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('inventory')]
class ProductActionController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly VariantCombinationLoader $combinationLoader)
    {
    }

    #[Route(path: '/api/_action/product/{productId}/combinations', name: 'api.action.product.combinations', methods: ['GET'])]
    public function getCombinations(string $productId, Context $context): JsonResponse
    {
        return new JsonResponse(
            $this->combinationLoader->load($productId, $context)
        );
    }
}
