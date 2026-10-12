<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\OAuth\Client\PublicClientRegistry;
use Shopware\Core\Framework\Api\OAuth\User\User;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Authorization endpoint of the OAuth2 authorization code grant (RFC 6749 §4.1) for public clients.
 *
 * The browser is sent to `GET /api/oauth/authorize`, which validates the request and forwards the user
 * to the Administration. The Administration shows a consent page and, once the logged-in user decided,
 * calls `POST /api/oauth/authorize` with its own bearer token to obtain the redirect back to the client.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class OAuthAuthorizeController extends AbstractController
{
    public const ADMIN_CONSENT_ROUTE = '#/oauth/authorize';

    private const AUTHORIZATION_PARAMETERS = [
        'response_type',
        'client_id',
        'redirect_uri',
        'state',
        'scope',
        'code_challenge',
        'code_challenge_method',
    ];

    public function __construct(
        private readonly AuthorizationServer $authorizationServer,
        private readonly PsrHttpFactory $psrHttpFactory,
        private readonly Psr17Factory $psr17Factory,
        private readonly PublicClientRegistry $publicClients,
        private readonly RateLimiter $rateLimiter,
        private readonly RouterInterface $router,
    ) {
    }

    /**
     * Entry point for the user's browser. Validates the authorization request and redirects
     * to the consent page of the Administration, keeping the original query string intact.
     */
    #[Route(path: '/api/oauth/authorize', name: 'api.oauth.authorize', defaults: ['auth_required' => false], methods: ['GET'])]
    public function authorize(Request $request): Response
    {
        try {
            $this->rateLimiter->ensureAccepted(RateLimiter::OAUTH, (string) $request->getClientIp());
        } catch (RateLimitExceededException $exception) {
            throw ApiException::notificationThrottled($exception->getWaitTime(), $exception);
        }

        try {
            $this->authorizationServer->validateAuthorizationRequest($this->psrHttpFactory->createRequest($request));
        } catch (OAuthServerException $exception) {
            return $this->createErrorRedirect($request, $exception);
        }

        $this->rateLimiter->reset(RateLimiter::OAUTH, (string) $request->getClientIp());

        return new RedirectResponse($this->getAdminUrl() . self::ADMIN_CONSENT_ROUTE . '?' . $request->getQueryString());
    }

    /**
     * Returns the information the consent page displays before the user decides.
     */
    #[Route(path: '/api/oauth/authorize/info', name: 'api.oauth.authorize.info', methods: ['GET'])]
    public function info(Request $request): JsonResponse
    {
        $authorizationRequest = $this->authorizationServer->validateAuthorizationRequest(
            $this->createAuthorizationRequest($request->query->all())
        );

        $client = $authorizationRequest->getClient();

        return new JsonResponse([
            'client' => [
                'id' => $client->getIdentifier(),
                'name' => $client->getName(),
            ],
            'redirectUri' => $authorizationRequest->getRedirectUri(),
            'scopes' => array_values(array_map(
                static fn ($scope) => $scope->getIdentifier(),
                $authorizationRequest->getScopes()
            )),
        ]);
    }

    /**
     * Completes the authorization request on behalf of the authenticated user and returns the
     * redirect URI (including the authorization code, or `error=access_denied`) for the browser.
     */
    #[Route(path: '/api/oauth/authorize', name: 'api.oauth.authorize.approve', methods: ['POST'])]
    public function approve(Request $request, Context $context): JsonResponse
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ApiException::invalidAdminSource($source::class);
        }

        $userId = $source->getUserId();
        if ($userId === null || $userId === '') {
            throw ApiException::userNotLoggedIn();
        }

        $authorizationRequest = $this->authorizationServer->validateAuthorizationRequest(
            $this->createAuthorizationRequest($request->request->all())
        );
        $authorizationRequest->setUser(new User($userId));
        $authorizationRequest->setAuthorizationApproved($request->request->getBoolean('approved'));

        try {
            $response = $this->authorizationServer->completeAuthorizationRequest(
                $authorizationRequest,
                $this->psr17Factory->createResponse()
            );
        } catch (OAuthServerException $exception) {
            if (!$exception->hasRedirect()) {
                throw $exception;
            }

            $response = $exception->generateHttpResponse($this->psr17Factory->createResponse());
        }

        return new JsonResponse(['redirectUri' => $response->getHeaderLine('Location')]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function createAuthorizationRequest(array $parameters): ServerRequestInterface
    {
        $query = [];
        foreach (self::AUTHORIZATION_PARAMETERS as $parameter) {
            if (isset($parameters[$parameter]) && \is_scalar($parameters[$parameter])) {
                $query[$parameter] = (string) $parameters[$parameter];
            }
        }

        return $this->psr17Factory
            ->createServerRequest(Request::METHOD_GET, '/api/oauth/authorize')
            ->withQueryParams($query);
    }

    /**
     * Sends the error back to the client's redirect URI where that URI is known to belong to the client.
     * Otherwise the error is rendered as a regular API error response so no open redirect is possible.
     */
    private function createErrorRedirect(Request $request, OAuthServerException $exception): Response
    {
        if ($exception->hasRedirect()) {
            return (new HttpFoundationFactory())->createResponse(
                $exception->generateHttpResponse($this->psr17Factory->createResponse())
            );
        }

        $clientId = $request->query->getString('client_id');
        $redirectUri = $request->query->getString('redirect_uri');

        if ($clientId === '' || $redirectUri === '' || !$this->publicClients->isRedirectUriAllowed($clientId, $redirectUri)) {
            throw $exception;
        }

        $parameters = ['error' => $exception->getErrorType(), 'error_description' => $exception->getMessage()];
        if ($exception->getHint() !== null) {
            $parameters['hint'] = $exception->getHint();
        }
        if ($request->query->has('state')) {
            $parameters['state'] = $request->query->getString('state');
        }

        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return new RedirectResponse($redirectUri . $separator . http_build_query($parameters));
    }

    private function getAdminUrl(): string
    {
        try {
            return $this->router->generate('administration.index', [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RouteNotFoundException) {
            // fallback if the Administration bundle is not installed, the url should work once the bundle is installed
            return EnvironmentHelper::getVariable('APP_URL') . '/admin';
        }
    }
}
