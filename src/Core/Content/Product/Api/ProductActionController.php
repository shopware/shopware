<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Api;

use Shopware\Core\Content\Product\Util\ProductLinkLoader;
use Shopware\Core\Content\Product\Util\VariantCombinationLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class ProductActionController extends AbstractController
{
    /**
     * @var VariantCombinationLoader
     */
    private $combinationLoader;

    /**
     * @var ProductLinkLoader
     */
    private $productLinkLoader;

    public function __construct(VariantCombinationLoader $combinationLoader, ProductLinkLoader $productLinkLoader)
    {
        $this->combinationLoader = $combinationLoader;
        $this->productLinkLoader = $productLinkLoader;
    }

    /**
     * @Route("/api/v{version}/_action/product/{productId}/combinations", name="api.action.product.combinations", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getCombinations(string $productId, Context $context)
    {
        return new JsonResponse(
            $this->combinationLoader->load($productId, $context)
        );
    }

    /**
     * @Route("/api/v{version}/_action/product/{productId}/links", name="api.action.product.links", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getLinks(string $productId, Context $context)
    {
        return new JsonResponse($this->productLinkLoader->load($productId, $context));
    }
}
