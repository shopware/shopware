<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppUrlChangeResolver\Resolver;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeStrategyNotFoundException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeStrategyNotFoundHttpException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal only for use by the app-system
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('core')]
class AppUrlChangeController extends AbstractController
{
    public function __construct(
        private readonly Resolver $appUrlChangeResolver,
        private readonly ShopIdProvider $shopIdProvider
    ) {
    }

    #[Route(path: 'api/app-system/app-url-change/strategies', name: 'api.app_system.app-url-change-strategies', methods: ['GET'])]
    public function getAvailableStrategies(): JsonResponse
    {
        return new JsonResponse(
            $this->appUrlChangeResolver->getAvailableStrategies()
        );
    }

    #[Route(path: 'api/app-system/app-url-change/resolve', name: 'api.app_system.app-url-change-resolve', methods: ['POST'])]
    public function resolve(Request $request, Context $context): Response
    {
        $strategy = $request->get('strategy');

        if (!$strategy) {
            throw AppException::missingRequestParameter('strategy');
        }

        try {
            $this->appUrlChangeResolver->resolve($strategy, $context);
        } catch (AppUrlChangeStrategyNotFoundException $e) {
            throw new AppUrlChangeStrategyNotFoundHttpException($e);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: 'api/app-system/app-url-change/url-difference', name: 'api.app_system.app-url-difference', methods: ['GET'])]
    public function getUrlDifference(): Response
    {
        try {
            $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException $e) {
            return new JsonResponse(
                [
                    'oldUrl' => $e->getPreviousUrl(),
                    'newUrl' => $e->getCurrentUrl(),
                ]
            );
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
