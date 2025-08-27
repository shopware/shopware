<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class ShopIdController extends AbstractController
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly Resolver $shopIdChangeResolver,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly EntityRepository $appRepository,
    ) {
    }

    #[Route(path: 'api/app-system/shop-id/change-strategies', name: 'api.app_system.shop_id.change_strategies', methods: ['GET'])]
    public function getAvailableStrategies(): JsonResponse
    {
        return new JsonResponse($this->shopIdChangeResolver->getAvailableStrategies());
    }

    #[Route(path: 'api/app-system/shop-id/change', name: 'api.app_system.shop_id.change', methods: ['POST'])]
    public function changeShopId(Request $request, Context $context): Response
    {
        $strategy = $request->get('strategy');

        if (!$strategy) {
            throw AppException::missingRequestParameter('strategy');
        }

        $this->shopIdChangeResolver->resolve($strategy, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: 'api/app-system/shop-id/check', name: 'api.app_system.shop_id.check', methods: ['POST'])]
    public function checkShopId(Context $context): Response
    {
        try {
            $this->shopIdProvider->getShopId();
        } catch (ShopIdChangeSuggestedException $e) {
            return new JsonResponse([
                'apps' => $this->appsRegisteredAtAppServers($context),
                'fingerprints' => $e->comparisonResult,
            ]);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return list<string>
     */
    private function appsRegisteredAtAppServers(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotEqualsFilter('appSecret', null));

        $apps = $this->appRepository
            ->search($criteria, $context)
            ->getEntities()
            ->map(function (AppEntity $app) {
                return $app->getTranslation('label');
            });

        return array_values($apps);
    }
}
