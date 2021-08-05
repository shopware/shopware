<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\AppUrlChangeResolver\Resolver;
use Shopware\Core\Framework\App\Exception\AppUrlChangeStrategyNotFoundException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeStrategyNotFoundHttpException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 *
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
     * @Since("6.3.3.0")
     * @Route("api/app-system/app-url-change/strategies", name="api.app_system.app-url-change-strategies", methods={"GET"})
     */
    public function getAvailableStrategies(): JsonResponse
    {
        return new JsonResponse(
            $this->appUrlChangeResolver->getAvailableStrategies()
        );
    }

    /**
     * @Since("6.3.3.0")
     * @Route("api/app-system/app-url-change/resolve", name="api.app_system.app-url-change-resolve", methods={"POST"})
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
     * @Since("6.3.3.0")
     * @Route("api/app-system/app-url-change/url-difference", name="api.app_system.app-url-difference", methods={"GET"})
     */
    public function getUrlDifference(): Response
    {
        if (!$this->systemConfigService->get(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY)) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }
        $shopIdConfig = (array) $this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY);
        $oldUrl = $shopIdConfig['app_url'];
        $newUrl = EnvironmentHelper::getVariable('APP_URL');

        if ($oldUrl === $newUrl) {
            $this->systemConfigService->delete(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY);

            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(
            [
                'oldUrl' => $oldUrl,
                'newUrl' => $newUrl,
            ]
        );
    }
}
