<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Api;

use Shopware\Core\Content\Product\Util\VariantCombinationLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class ProductActionController extends AbstractController
{
    /**
     * @var VariantCombinationLoader
     */
    private $combinationLoader;

    /**
     * @internal
     */
    public function __construct(VariantCombinationLoader $combinationLoader)
    {
        $this->combinationLoader = $combinationLoader;
    }

    /**
     * @deprecated tag:v6.5.0 - reason:return-type-change - native return type JsonResponse will be added
     *
     * @Since("6.0.0.0")
     * @Route("/api/_action/product/{productId}/combinations", name="api.action.product.combinations", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getCombinations(string $productId, Context $context)
    {
        return new JsonResponse(
            $this->combinationLoader->load($productId, $context)
        );
    }
}
