<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Authentication;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpException;
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
 * Allows MCP clients to authenticate with integration credentials (access key + secret)
 * directly, without requiring a separate OAuth token exchange.
 *
 * When `sw-access-key` and `sw-secret-access-key` headers are present on the MCP endpoint,
 * this listener validates them against the integration table and sets up the request
 * attributes so that ApiRequestContextResolver resolves the proper AdminApiSource
 * with the integration's ACL permissions.
 *
 * Falls through to standard bearer token auth if no integration headers are present.
 */
#[Package('framework')]
class McpAuthenticationListener implements EventSubscriberInterface
{
    private const MCP_ROUTE_NAME = 'api.mcp.endpoint';
    private const HEADER_SECRET_ACCESS_KEY = 'sw-secret-access-key';

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
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
            return;
        }

        if (AccessKeyHelper::getOrigin($accessKey) !== 'integration') {
            throw McpException::unsupportedKeyType();
        }

        $integration = $this->connection->fetchAssociative(
            'SELECT integration.id, integration.secret_access_key, app.active AS app_active
             FROM integration
             LEFT JOIN app ON app.integration_id = integration.id
             WHERE integration.access_key = :accessKey',
            ['accessKey' => $accessKey]
        );

        if ($integration === false) {
            throw McpException::invalidAccessKey();
        }

        if ($integration['app_active'] === '0') {
            throw McpException::inactiveApp();
        }

        if (!password_verify($secretKey, (string) $integration['secret_access_key'])) {
            throw McpException::invalidSecret();
        }

        $this->connection->update(
            'integration',
            ['last_usage_at' => (new \DateTime())->format('Y-m-d H:i:s.v')],
            ['id' => $integration['id']]
        );

        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, 'mcp-' . $accessKey);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $accessKey);
        $request->attributes->set('auth_required', false);
    }
}
