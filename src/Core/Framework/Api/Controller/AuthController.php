<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use League\OAuth2\Server\AuthorizationServer;
use Shopware\Core\Framework\Api\Controller\Exception\AuthThrottledException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 *
 * @package system-settings
 */
class AuthController extends AbstractController
{
    private AuthorizationServer $authorizationServer;

    private PsrHttpFactory $psrHttpFactory;

    private RateLimiter $rateLimiter;

    /**
     * @internal
     */
    public function __construct(
        AuthorizationServer $authorizationServer,
        PsrHttpFactory $psrHttpFactory,
        RateLimiter $rateLimiter
    ) {
        $this->authorizationServer = $authorizationServer;
        $this->psrHttpFactory = $psrHttpFactory;
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/oauth/authorize", name="api.oauth.authorize", defaults={"auth_required"=false}, methods={"POST"})
     */
    public function authorize(Request $request): void
    {
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/oauth/token", name="api.oauth.token", defaults={"auth_required"=false}, methods={"POST"})
     */
    public function token(Request $request): Response
    {
        $response = new Response();

        try {
            $cacheKey = $request->get('username') . '-' . $request->getClientIp();

            $this->rateLimiter->ensureAccepted(RateLimiter::OAUTH, $cacheKey);
        } catch (RateLimitExceededException $exception) {
            throw new AuthThrottledException($exception->getWaitTime(), $exception);
        }

        $psr7Request = $this->psrHttpFactory->createRequest($request);
        $psr7Response = $this->psrHttpFactory->createResponse($response);

        $response = $this->authorizationServer->respondToAccessTokenRequest($psr7Request, $psr7Response);

        $this->rateLimiter->reset(RateLimiter::OAUTH, $cacheKey);

        return (new HttpFoundationFactory())->createResponse($response);
    }
}
