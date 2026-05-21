<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\A2A;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Mcp\UcpMcpToolDispatcher;
use Shopware\Core\Framework\Ucp\Transport\Rest\UcpRouteScope;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * A2A Protocol endpoint at `/ucp/a2a`. Implements the JSON-RPC 2.0 surface
 * defined in [A2A Protocol](https://a2a-protocol.org/) §"Agent Communication"
 * with the UCP extension active.
 *
 * Supported methods:
 *   - `message/send`        — synchronous turn (returns Message or Task)
 *   - `message/stream`      — streaming turn (sse) — NOT YET implemented
 *   - `tasks/get`           — fetch task by id
 *   - `tasks/cancel`        — abort a task
 *
 * Message routing: structured-data `DataPart`s of the form
 * `{ "action": "<verb>", ... }` are mapped to the matching UCP MCP tool via
 * {@see A2AMessageTranslator}. The MCP tool's response is wrapped in an A2A
 * `DataPart` with key `a2a.ucp.<resource>`.
 *
 * Natural-language `TextPart`s are not handled here — they would require an
 * LLM-backed intent classifier which is out of scope for the protocol layer.
 * Plugins that want to handle free-form text can subscribe to
 * {@see UcpEvents::CHECKOUT_REQUEST} (for checkout-targeting text) or extend
 * the {@see A2AMessageTranslator}.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [UcpRouteScope::ID]])]
class A2AController
{
    public function __construct(
        private readonly A2AMessageTranslator $translator,
        private readonly A2ATaskStore $taskStore,
        private readonly UcpMcpToolDispatcher $mcpDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/ucp/a2a',
        name: 'ucp.a2a.endpoint',
        defaults: ['auth_required' => false, '_loginRequired' => false],
        methods: ['POST', 'OPTIONS']
    )]
    public function handle(Request $request): Response
    {
        if (!Feature::isActive('UCP_SERVER')) {
            return new JsonResponse([
                'jsonrpc' => '2.0', 'id' => null,
                'error' => ['code' => -32601, 'message' => 'UCP A2A disabled'],
            ], Response::HTTP_NOT_FOUND);
        }
        if ($request->getMethod() === 'OPTIONS') {
            return new Response(null, 204);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->jsonRpcError(null, -32700, 'Parse error');
        }

        $context = $request->attributes->get(UcpRequestContext::REQUEST_ATTRIBUTE);
        if (!$context instanceof UcpRequestContext) {
            throw UcpException::featureDisabled();
        }

        $id = $payload['id'] ?? null;
        $method = $payload['method'] ?? '';
        $params = \is_array($payload['params'] ?? null) ? $payload['params'] : [];

        try {
            $result = match ($method) {
                'message/send' => $this->messageSend($params, $context),
                'tasks/get' => $this->tasksGet($params, $context),
                'tasks/cancel' => $this->tasksCancel($params, $context),
                // JSON-RPC error transport: BadMethodCall -> -32601, InvalidArgument -> -32602.
                // See catch blocks above; these are not user-facing domain errors.
                // @phpstan-ignore-next-line shopware.domainException
                default => throw new \BadMethodCallException('Method not found: ' . $method, -32601),
            };
        } catch (\BadMethodCallException $e) {
            return $this->jsonRpcError($id, (int) ($e->getCode() ?: -32601), $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->jsonRpcError($id, -32602, $e->getMessage());
        } catch (UcpException $e) {
            $this->logger->info('UCP A2A capability error', ['error' => $e->getMessage()]);

            return $this->jsonRpcError($id, -32001, $e->getMessage(), [
                'code' => strtolower(str_replace('UCP__', '', $e->getErrorCode())),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('UCP A2A internal error', ['exception' => $e]);

            return $this->jsonRpcError($id, -32603, 'Internal error');
        }

        return new JsonResponse(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function messageSend(array $params, UcpRequestContext $context): array
    {
        $message = $params['message'] ?? null;
        if (!\is_array($message)) {
            // JSON-RPC error transport: BadMethodCall -> -32601, InvalidArgument -> -32602.
            // See catch blocks above; these are not user-facing domain errors.
            // @phpstan-ignore-next-line shopware.domainException
            throw new \InvalidArgumentException('params.message is required');
        }
        $messageId = $message['messageId'] ?? null;
        if (!\is_string($messageId) || $messageId === '') {
            // JSON-RPC error transport: BadMethodCall -> -32601, InvalidArgument -> -32602.
            // See catch blocks above; these are not user-facing domain errors.
            // @phpstan-ignore-next-line shopware.domainException
            throw new \InvalidArgumentException('message.messageId is required for idempotency');
        }

        $contextId = \is_string($message['contextId'] ?? null) && $message['contextId'] !== ''
            ? $message['contextId']
            : bin2hex(random_bytes(16));

        $claim = $this->taskStore->claimMessage($context->config->getSalesChannelId(), $messageId, $contextId);
        if ($claim['status'] === A2ATaskStore::CLAIM_REPLAY && $claim['response'] !== null) {
            return $claim['response'];
        }
        if ($claim['status'] === A2ATaskStore::CLAIM_IN_FLIGHT) {
            // JSON-RPC error transport: BadMethodCall -> -32601, InvalidArgument -> -32602.
            // See catch blocks above; these are not user-facing domain errors.
            // @phpstan-ignore-next-line shopware.domainException
            throw new \InvalidArgumentException('message.messageId is already in flight');
        }

        $intent = $this->translator->translate($message);
        if ($intent === null) {
            // No structured action found — emit a clarifying message with the
            // capabilities the platform can use.
            $response = $this->buildClarifyingMessage($contextId);
            $this->taskStore->recordMessageResponse($context->config->getSalesChannelId(), $messageId, $response);

            return $response;
        }

        try {
            $toolResult = $this->mcpDispatcher->callTool($intent->toolName, $intent->arguments, $context);
        } catch (\Throwable $e) {
            $this->taskStore->abortMessage($context->config->getSalesChannelId(), $messageId);

            throw $e;
        }

        $responseMessage = [
            'role' => 'agent',
            'kind' => 'message',
            'messageId' => bin2hex(random_bytes(16)),
            'contextId' => $contextId,
            'parts' => [
                [
                    'type' => 'data',
                    'data' => [
                        'a2a.ucp.' . $intent->resource => $toolResult,
                    ],
                ],
                [
                    'type' => 'text',
                    'text' => $intent->summary($toolResult),
                ],
            ],
        ];

        $this->taskStore->recordMessageResponse(
            $context->config->getSalesChannelId(),
            $messageId,
            $responseMessage
        );

        return $responseMessage;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function tasksGet(array $params, UcpRequestContext $context): array
    {
        $taskId = $params['id'] ?? null;
        if (!\is_string($taskId) || $taskId === '') {
            // JSON-RPC error transport: BadMethodCall -> -32601, InvalidArgument -> -32602.
            // See catch blocks above; these are not user-facing domain errors.
            // @phpstan-ignore-next-line shopware.domainException
            throw new \InvalidArgumentException('params.id is required');
        }

        $task = $this->taskStore->getTask($context->config->getSalesChannelId(), $taskId);
        if ($task === null) {
            // JSON-RPC error transport: BadMethodCall -> -32601, InvalidArgument -> -32602.
            // See catch blocks above; these are not user-facing domain errors.
            // @phpstan-ignore-next-line shopware.domainException
            throw new \InvalidArgumentException('Task not found: ' . $taskId);
        }

        return $task;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function tasksCancel(array $params, UcpRequestContext $context): array
    {
        $taskId = $params['id'] ?? null;
        if (!\is_string($taskId) || $taskId === '') {
            // JSON-RPC error transport: BadMethodCall -> -32601, InvalidArgument -> -32602.
            // See catch blocks above; these are not user-facing domain errors.
            // @phpstan-ignore-next-line shopware.domainException
            throw new \InvalidArgumentException('params.id is required');
        }
        $this->taskStore->cancelTask($context->config->getSalesChannelId(), $taskId);

        return [
            'id' => $taskId,
            'state' => 'cancelled',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildClarifyingMessage(string $contextId): array
    {
        return [
            'role' => 'agent',
            'kind' => 'message',
            'messageId' => bin2hex(random_bytes(16)),
            'contextId' => $contextId,
            'parts' => [[
                'type' => 'text',
                'text' => 'I can help with cart, checkout, catalog or order operations. Please send a structured `data` part with an `action` field, e.g. {"action":"add_to_cart","product_id":"...","quantity":1}.',
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonRpcError(mixed $id, int $code, string $message, array $data = []): JsonResponse
    {
        $err = ['code' => $code, 'message' => $message];
        if ($data !== []) {
            $err['data'] = $data;
        }

        return new JsonResponse(['jsonrpc' => '2.0', 'id' => $id, 'error' => $err]);
    }
}
