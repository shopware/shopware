<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Analytics\Api;

use Shopware\Core\Framework\Analytics\TokenService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('data-services')]
class AnalyticsController
{
    public function __construct(private readonly TokenService $tokenService)
    {
    }

    #[Route(
        path: 'api/analytics/token',
        name: 'api.analytics.token',
        methods: ['GET']
    )]
    public function token(Request $request): Response
    {
        $referer = $request->headers->get('referer');

        if ($referer === null || $referer === '') {
            return new JsonResponse([], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $token = $this->tokenService->generate($referer);
        if ($token === null) {
            return new JsonResponse([], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse($token, Response::HTTP_OK);
    }
}
