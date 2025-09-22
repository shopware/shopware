<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal only for use by the app-system
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class ShopController
{
    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    #[Route(
        path: 'api/app-system/shop/verify',
        name: 'api.app_system.shop_verify',
        defaults: ['auth_required' => false],
        methods: ['GET']
    )]
    public function index(Request $request): Response
    {
        $runId = $request->get('rid');
        $uToken = $request->get('token');

        $cacheKey = "app_url_check-$runId";

        $item = $this->cache->getItem($cacheKey);

        if (!$item->isHit()) {
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $token = $item->get();

        if (\strlen($token) !== 32) {
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        if (!hash_equals($token, $uToken)) {
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([], Response::HTTP_OK);
    }
}
