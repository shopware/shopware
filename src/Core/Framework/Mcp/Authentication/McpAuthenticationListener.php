<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Authentication;

use Shopware\Core\Framework\Api\OAuth\ClientRepository;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Allows MCP clients to authenticate with either access key credentials or a standard bearer JWT.
 *
 * When `sw-access-key` and `sw-secret-access-key` headers are present, this listener validates
 * them via ClientRepository and sets the pre-authenticated request attributes so that
 * ApiRequestContextResolver resolves the correct AdminApiSource.
 *
 * When neither header is present, the listener falls through to standard bearer JWT auth
 * (password-grant or client_credentials). McpAllowlistProvider handles allowlist resolution
 * for all auth modes, including the per-user allowlist applied to bearer JWT sessions.
 */
#[Package('framework')]
class McpAuthenticationListener implements EventSubscriberInterface
{
    private const MCP_ROUTE_NAME = 'api.mcp.endpoint';
    private const HEADER_SECRET_ACCESS_KEY = 'sw-secret-access-key';

    /**
     * @internal
     */
    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['authenticate', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE_PRE],
            ],
        ];
    }

    public function authenticate(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->attributes->get('_route') !== self::MCP_ROUTE_NAME) {
            return;
        }

        $accessKey = $request->headers->get(PlatformRequest::HEADER_ACCESS_KEY);
        $secretKey = $request->headers->get(self::HEADER_SECRET_ACCESS_KEY);

        if ($accessKey === null || $secretKey === null) {
            // No access key headers — fall through to standard bearer JWT auth.
            return;
        }

        $origin = AccessKeyHelper::getOrigin($accessKey);
        if ($origin !== 'integration' && $origin !== 'user') {
            throw McpException::unsupportedKeyType();
        }

        $this->rateLimiter->ensureAccepted(RateLimiter::OAUTH, $accessKey);

        if (!$this->clientRepository->validateClient($accessKey, $secretKey, 'client_credentials')) {
            throw McpException::invalidCredentials();
        }

        $this->rateLimiter->reset(RateLimiter::OAUTH, $accessKey);

        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, 'mcp-' . $accessKey);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $accessKey);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED, true);
    }
}
