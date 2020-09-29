<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Shopware\Core\Framework\App\AppUrlChangeResolver\Resolver;
use Shopware\Core\Framework\App\Exception\AppUrlChangeStrategyNotFoundException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeStrategyNotFoundHttpException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class AppUrlChangeController extends AbstractController
{
    /**
     * @var Resolver
     */
    private $appUrlChangeResolver;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        Resolver $appUrlChangeResolverStrategy,
        SystemConfigService $systemConfigService
    ) {
        $this->appUrlChangeResolver = $appUrlChangeResolverStrategy;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @Route("api/v{version}/app-system/app-url-change/strategies", name="api.app_system.app-url-change-strategies", methods={"GET"})
     */
    public function getAvailableStrategies(): JsonResponse
    {
        return new JsonResponse(
            $this->appUrlChangeResolver->getAvailableStrategies()
        );
    }

    /**
     * @Route("api/v{version}/app-system/app-url-change/resolve", name="api.app_system.app-url-change-resolve", methods={"POST"})
     */
    public function resolve(Request $request, Context $context): Response
    {
        $strategy = $request->get('strategy');

        if (!$strategy) {
            throw new MissingRequestParameterException('strategy');
        }

        try {
            $this->appUrlChangeResolver->resolve($strategy, $context);
        } catch (AppUrlChangeStrategyNotFoundException $e) {
            throw new AppUrlChangeStrategyNotFoundHttpException($e);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("api/v{version}/app-system/app-url-change/url-difference", name="api.app_system.app-url-difference", methods={"GET"})
     */
    public function getUrlDifference(): Response
    {
        if (!$this->systemConfigService->get(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY)) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }
        $shopIdConfig = (array) $this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY);
        $oldUrl = $shopIdConfig['app_url'];

        return new JsonResponse(
            [
                'oldUrl' => $oldUrl,
                'newUrl' => $_SERVER['APP_URL'],
            ]
        );
    }
}
