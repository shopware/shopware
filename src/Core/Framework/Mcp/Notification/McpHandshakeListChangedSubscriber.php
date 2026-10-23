<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Notification;

use Mcp\Event\ResponseEvent;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\InitializeRequest;
use Mcp\Schema\Result\InitializeResult;
use Mcp\Schema\ServerCapabilities;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Declares `listChanged` for every advertised list in the `initialize` answer of both MCP servers.
 *
 * mcp/sdk 0.9 strips the flag from every `initialize` answer, because it delivers list changes only
 * through `subscriptions/listen` of the stateless era. Shopware delivers them on the handshake era as
 * well, through the session's outgoing queue ({@see McpListChangedNotifier}), and clients only act on
 * a notification the server declared.
 *
 * Only the answers of the two Shopware endpoints are changed. Another MCP server configured through the
 * bundle, for example by a plugin, has no such queue and keeps the SDK's answer.
 *
 * @internal
 */
#[Package('framework')]
final class McpHandshakeListChangedSubscriber implements EventSubscriberInterface
{
    /**
     * The routes of {@see \Shopware\Core\Framework\Mcp\Controller\McpServerController} and
     * {@see \Shopware\Core\Framework\Mcp\Controller\StoreApiMcpServerController}.
     */
    private const SHOPWARE_MCP_ROUTES = ['api.mcp.endpoint', 'store-api.mcp.endpoint'];

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [ResponseEvent::class => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->getRequest() instanceof InitializeRequest) {
            return;
        }

        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route');
        if (!\in_array($route, self::SHOPWARE_MCP_ROUTES, true)) {
            return;
        }

        $response = $event->getResponse();
        $result = $response->result;
        if (!$result instanceof InitializeResult) {
            return;
        }

        $capabilities = $result->capabilities;

        $event->setResponse(new Response($response->id, new InitializeResult(
            new ServerCapabilities(
                tools: $capabilities->tools,
                toolsListChanged: $capabilities->tools === true,
                resources: $capabilities->resources,
                resourcesSubscribe: $capabilities->resourcesSubscribe,
                resourcesListChanged: $capabilities->resources === true,
                prompts: $capabilities->prompts,
                promptsListChanged: $capabilities->prompts === true,
                logging: $capabilities->logging,
                completions: $capabilities->completions,
                experimental: $capabilities->experimental,
                extensions: $capabilities->extensions,
                tasks: $capabilities->tasks,
            ),
            $result->serverInfo,
            $result->instructions,
            $result->meta,
            $result->protocolVersion,
        )));
    }
}
