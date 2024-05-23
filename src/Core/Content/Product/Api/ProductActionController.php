<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Api;

use Shopware\Core\Content\Product\Service\PriceStockImporter;
use Shopware\Core\Content\Product\Util\VariantCombinationLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('inventory')]
class ProductActionController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly VariantCombinationLoader $combinationLoader,
        private readonly PriceStockImporter $importer
    )
    {
    }

    #[Route(path: '/api/_action/product/{productId}/combinations', name: 'api.action.product.combinations', methods: ['GET'])]
    public function getCombinations(string $productId, Context $context): JsonResponse
    {
        return new JsonResponse(
            $this->combinationLoader->load($productId, $context)
        );
    }

    #[Route(path: '/api/_action/sync/stock-pricing', name: 'api.action.sync.prices', methods: ['POST'])]
    public function priceImport(Request $request, Context $context): JsonResponse
    {
        $this->importer->import($request->request->all(), $context);

        return new JsonResponse();
    }
}
