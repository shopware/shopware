<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
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
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    #[Route(
        path: 'api/app-system/shop/verify',
        name: 'api.app_system.shop_verify',
        defaults: ['auth_required' => false],
        methods: ['GET']
    )]
    public function verify(Request $request): Response
    {
        $ip = $request->getClientIp();
        if ($ip === null) {
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $this->rateLimiter->ensureAccepted(RateLimiter::APP_SHOP_VERIFY, $ip);

        $runId = $request->get('rid');
        $uToken = $request->get('token');

        if ($runId === null || $uToken === null) {
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $cacheKey = \sprintf('%s-%s', AppUrlVerifier::VERIFICATION_CACHE_KEY_PREFIX, $runId);

        $item = $this->cache->getItem($cacheKey);

        if (!$item->isHit()) {
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $token = $item->get();

        if (\strlen($token) !== 32 || \strlen($uToken) !== 32) {
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        if (!hash_equals($token, $uToken)) {
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
