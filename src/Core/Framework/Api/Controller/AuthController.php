<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use League\OAuth2\Server\AuthorizationServer;
use Shopware\Core\Framework\Api\Controller\Exception\AuthThrottledException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('system-settings')]
class AuthController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AuthorizationServer $authorizationServer,
        private readonly PsrHttpFactory $psrHttpFactory,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    #[Route(path: '/api/oauth/authorize', name: 'api.oauth.authorize', defaults: ['auth_required' => false], methods: ['POST'])]
    public function authorize(Request $request): void
    {
    }

    #[Route(path: '/api/oauth/token', name: 'api.oauth.token', defaults: ['auth_required' => false], methods: ['POST'])]
    public function token(Request $request): Response
    {
        try {
            $cacheKey = $request->get('username') . '-' . $request->getClientIp();

            $this->rateLimiter->ensureAccepted(RateLimiter::OAUTH, $cacheKey);
        } catch (RateLimitExceededException $exception) {
            throw new AuthThrottledException($exception->getWaitTime(), $exception);
        }

        $response = $this->respondToAccessTokenRequest($request);

        $this->rateLimiter->reset(RateLimiter::OAUTH, $cacheKey);

        return $response;
    }

    #[Route(path: '/api/oauth/logout', name: 'api.oauth.logout', defaults: ['auth_required' => false], methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $response = new Response();
        self::setAdminAuthCookie($request, $response);

        return $response;
    }

    public function respondToAccessTokenRequest(Request $request): Response
    {
        $psr7Request = $this->psrHttpFactory->createRequest($request);
        $psr7Response = $this->psrHttpFactory->createResponse(new Response());

        $psr7Response = $this->authorizationServer->respondToAccessTokenRequest($psr7Request, $psr7Response);

        $response = (new HttpFoundationFactory())->createResponse($psr7Response);
        $auth = json_decode((string)$psr7Response->getBody(), true);

        self::setAdminAuthCookie($request, $response, $auth);

        return $response;
    }

    private static function setAdminAuthCookie(Request $request, Response $response, ?array $auth = null): void
    {
        $response->headers->setCookie(
            Cookie::create(
                'admin_auth',
                $auth['access_token'] ?? null,
                isset($auth) ? time() + $auth['expires_in'] : 1,
                $request->getBaseUrl() . ScriptController::PATH,
                null,
                $request->isSecure(),
                true, //HttpOnly
                false,
                Cookie::SAMESITE_STRICT
            )
        );
    }
}
