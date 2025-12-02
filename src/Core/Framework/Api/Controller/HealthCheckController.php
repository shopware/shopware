<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\HealthCheck\Event\HealthCheckEvent;
use Shopware\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\SystemCheck\SystemChecker;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class HealthCheckController
{
    public const HEADER_AUTHORIZATION = 'Authorization';

    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemChecker $systemChecker,
        private readonly SymfonyBearerTokenValidator $tokenValidator,
        private readonly ?string $staticToken,
    ) {
    }

    /**
     * This is a simple health check to check that the basic application runs.
     * Use it in Docker HEALTHCHECK with curl --silent --fail http://localhost/api/_info/health-check
     */
    #[Route(path: '/api/_info/health-check', name: 'api.info.health.check', defaults: ['auth_required' => false], methods: ['GET'])]
    public function check(Context $context): Response
    {
        $event = new HealthCheckEvent($context);
        $this->eventDispatcher->dispatch($event);

        $response = new Response('');
        $response->setPrivate();

        return $response;
    }

    #[Route(path: '/api/_info/system-health-check', name: 'api.info.system-health.check', defaults: ['auth_required' => false], methods: ['GET'])]
    public function health(Request $request): Response
    {
        $this->validateStaticOrBearerAuthorization($request);

        $executionContextRaw = (string) $request->get('context', SystemCheckExecutionContext::WEB->value);
        $executionContext = SystemCheckExecutionContext::tryFrom($executionContextRaw);
        if (!$executionContext instanceof SystemCheckExecutionContext) {
            throw ApiException::badRequest('Invalid execution context: ' . $executionContextRaw);
        }

        $result = $this->systemChecker->check($executionContext);

        return (new JsonResponse(['checks' => $result]))->setPrivate();
    }

    /**
     * Validates Authorization header for either a 'Static' token or 'Bearer' token that is valid
     * Otherwise throws exception.
     *
     * @throws OAuthServerException
     */
    private function validateStaticOrBearerAuthorization(Request $request): void
    {
        $authorizationHeader = $request->headers->get(self::HEADER_AUTHORIZATION);
        if (
            !empty($this->staticToken)
            && $authorizationHeader !== null
            && str_contains($authorizationHeader, 'Static')
        ) {
            $token = \trim((string) \preg_replace('/^\s*Static\s/', '', $authorizationHeader));

            // compare header token against configured static token
            if ($token !== $this->staticToken) {
                throw OAuthServerException::accessDenied('Static token is invalid');
            }
        } else {
            $this->tokenValidator->validateAuthorization($request);
        }
    }
}
