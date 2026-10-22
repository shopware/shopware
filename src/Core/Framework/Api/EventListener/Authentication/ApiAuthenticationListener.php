<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener\Authentication;

use League\OAuth2\Server\AuthorizationServer;
use Shopware\Core\Framework\Api\OAuth\GrantTypeFactory;
use Shopware\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiContextRouteScopeDependant;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Routing\RouteScopeCheckTrait;
use Shopware\Core\Framework\Routing\RouteScopeRegistry;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
class ApiAuthenticationListener implements EventSubscriberInterface
{
    use RouteScopeCheckTrait;

    private const MCP_ROUTE_NAME = 'api.mcp.endpoint';

    /**
     * @internal
     */
    public function __construct(
        private readonly SymfonyBearerTokenValidator $symfonyBearerTokenValidator,
        private readonly AuthorizationServer $authorizationServer,
        private readonly GrantTypeFactory $grantTypeFactory,
        private readonly RouteScopeRegistry $routeScopeRegistry,
        private readonly string $accessTokenTtl = 'PT10M',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['setupOAuth', 128],
            ],
            KernelEvents::CONTROLLER => [
                ['validateRequest', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE],
            ],
        ];
    }

    public function setupOAuth(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $accessTokenInterval = new \DateInterval($this->accessTokenTtl);

        foreach ($this->grantTypeFactory->createGrantTypes() as $grantType) {
            $this->authorizationServer->enableGrantType($grantType, $accessTokenInterval);
        }
    }

    public function validateRequest(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->get('auth_required', true)) {
            return;
        }

        if ($request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED, false)) { // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }

        if (!$this->isRequestScoped($request, ApiContextRouteScopeDependant::class)) {
            return;
        }

        // A token bound to the MCP resource (RFC 8707) is accepted on `/api/_mcp` only.
        // Subrequests started by an MCP request (app script tools) inherit its authentication and stay allowed.
        $this->symfonyBearerTokenValidator->validateAuthorization(
            $request,
            acceptResourceBoundToken: !$event->isMainRequest() || $request->attributes->get('_route') === self::MCP_ROUTE_NAME,
        );
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }
}
