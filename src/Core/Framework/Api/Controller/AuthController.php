<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Package('fundamentals@framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AuthController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AuthorizationServer $authorizationServer,
        private readonly PsrHttpFactory $psrHttpFactory,
        private readonly RateLimiter $rateLimiter,
        private readonly Connection $connection,
    ) {
    }

    #[Route(path: '/api/oauth/token', name: 'api.oauth.token', defaults: ['auth_required' => false], methods: ['POST'])]
    public function token(Request $request): Response
    {
        $response = new Response();

        $usernameKey = strtolower($request->request->getString('username'));
        $clientIpKey = (string) $request->getClientIp();
        $combinedKey = $usernameKey . '-' . $clientIpKey;

        try {
            $this->rateLimiter->ensureAccepted(RateLimiter::OAUTH, $combinedKey);
            $this->rateLimiter->ensureAcceptedIfConfigured(RateLimiter::OAUTH_USER, $usernameKey);
            $this->rateLimiter->ensureAcceptedIfConfigured(RateLimiter::OAUTH_CLIENT, $clientIpKey);
        } catch (RateLimitExceededException $exception) {
            throw ApiException::notificationThrottled($exception->getWaitTime(), $exception);
        }

        $psr7Request = $this->psrHttpFactory->createRequest($request);
        $psr7Response = $this->psrHttpFactory->createResponse($response);

        $grantType = $request->request->getString('grant_type');
        if (\in_array($grantType, ['authorization_code', 'refresh_token'], true)) {
            $response = $this->respondToAccessTokenRequestInTransaction($psr7Request, $psr7Response);
        } else {
            $response = $this->authorizationServer->respondToAccessTokenRequest($psr7Request, $psr7Response);
        }

        $this->rateLimiter->reset(RateLimiter::OAUTH, $combinedKey);
        $this->rateLimiter->resetIfConfigured(RateLimiter::OAUTH_USER, $usernameKey);
        $this->rateLimiter->resetIfConfigured(RateLimiter::OAUTH_CLIENT, $clientIpKey);

        return (new HttpFoundationFactory())->createResponse($response);
    }

    private function respondToAccessTokenRequestInTransaction(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $this->connection->beginTransaction();

        try {
            $response = $this->authorizationServer->respondToAccessTokenRequest($request, $response);
            $this->connection->commit();

            return $response;
        } catch (OAuthServerException $exception) {
            $this->connection->commit();

            throw $exception;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }
}
