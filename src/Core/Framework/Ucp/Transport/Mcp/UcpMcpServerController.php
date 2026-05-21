<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Idempotency\IdempotencyStore;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Rest\UcpAgentRequestResolver;
use Shopware\Core\Framework\Ucp\Transport\Rest\UcpRouteScope;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Ucp\UcpVersion;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * UCP MCP endpoint at `/ucp/mcp`. Implements the subset of MCP
 * protocol relevant for agentic commerce:
 *   - initialize          (handshake + capability discovery)
 *   - tools/list          (list available UCP tools)
 *   - tools/call          (invoke a tool)
 *   - notifications/initialized (no-op ack)
 *   - ping                (liveness)
 *
 * Unlike the admin MCP endpoint (`/api/_mcp`), this server is buyer-scoped:
 * authentication happens via the UCP-Agent header (RFC 9421 signed) handled
 * upstream by {@see UcpAgentRequestResolver}.
 *
 * The agent advertises its profile via `meta.ucp-agent.profile` inside the
 * JSON-RPC envelope OR via the HTTP `UCP-Agent` header (either accepted).
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [UcpRouteScope::ID]])]
class UcpMcpServerController
{
    public const PROTOCOL_VERSION = '2025-06-18';
    public const SERVER_NAME = 'shopware-ucp';
    public const SERVER_VERSION = '1.0.0';

    public function __construct(
        private readonly UcpMcpToolDispatcher $dispatcher,
        private readonly IdempotencyStore $idempotencyStore,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/ucp/mcp',
        name: 'ucp.mcp.endpoint',
        defaults: ['auth_required' => false, 'XmlHttpRequest' => true],
        methods: ['POST', 'OPTIONS']
    )]
    public function handle(Request $request): Response
    {
        if (!Feature::isActive('UCP_SERVER')) {
            return new JsonResponse(['jsonrpc' => '2.0', 'id' => null, 'error' => [
                'code' => -32601, 'message' => 'UCP MCP disabled',
            ]], Response::HTTP_NOT_FOUND);
        }

        if ($request->getMethod() === 'OPTIONS') {
            return new Response(null, 204);
        }

        $body = (string) $request->getContent();
        if ($body === '') {
            return $this->jsonRpcError(null, -32700, 'Empty body — JSON-RPC payload expected');
        }

        $payload = json_decode($body, true);
        if (!\is_array($payload)) {
            return $this->jsonRpcError(null, -32700, 'Parse error');
        }

        // Handle batch
        if (array_is_list($payload) && $payload !== []) {
            $responses = [];
            foreach ($payload as $item) {
                if (!\is_array($item)) {
                    $responses[] = $this->buildErrorObject(null, -32600, 'Invalid request');
                    continue;
                }
                $responses[] = $this->handleSingle($item, $request);
            }
            $filtered = array_values(array_filter($responses, static fn ($r): bool => $r !== null));

            return new JsonResponse($filtered);
        }

        $resp = $this->handleSingle($payload, $request);
        if ($resp === null) {
            // Notification: no response
            return new Response(null, 204);
        }

        return new JsonResponse($resp);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null null = notification, no response body
     */
    private function handleSingle(array $payload, Request $request): ?array
    {
        $id = $payload['id'] ?? null;
        $method = \is_string($payload['method'] ?? null) ? $payload['method'] : '';
        $params = \is_array($payload['params'] ?? null) ? $payload['params'] : [];
        $isNotification = !\array_key_exists('id', $payload);

        try {
            switch ($method) {
                case 'initialize':
                    $result = $this->initialize($params);
                    break;
                case 'tools/list':
                    $result = $this->toolsList($request);
                    break;
                case 'tools/call':
                    $result = $this->toolsCall($params, $request);
                    break;
                case 'ping':
                    $result = new \stdClass();
                    break;
                case 'notifications/initialized':
                case 'notifications/cancelled':
                    return null;
                default:
                    return $this->buildErrorObject($id, -32601, 'Method not found: ' . $method);
            }
        } catch (UcpException $e) {
            $this->logger->info('UCP MCP error', [
                'method' => $method,
                'code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ]);

            return $this->buildErrorObject($id, -32001, $e->getMessage(), [
                'code' => strtolower(str_replace('UCP__', '', $e->getErrorCode())),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('UCP MCP internal error', ['exception' => $e]);

            return $this->buildErrorObject($id, -32603, 'Internal error');
        }

        if ($isNotification) {
            return null;
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => self::SERVER_NAME,
                'version' => self::SERVER_VERSION,
            ],
            'instructions' => 'Shopware UCP server — call tools/list to discover available capability operations. UCP version: ' . UcpVersion::CURRENT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolsList(Request $request): array
    {
        $context = $this->getContext($request);

        return ['tools' => $this->dispatcher->listTools($context)];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function toolsCall(array $params, Request $request): array
    {
        $context = $this->getContext($request);
        $name = \is_string($params['name'] ?? null) ? $params['name'] : '';
        $arguments = \is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        $idempotencyKey = $this->resolveIdempotencyKey($params, $request);

        $claim = $this->claimIdempotency($name, $arguments, $context, $idempotencyKey);
        if ($claim !== null && $claim['status'] === IdempotencyStore::RESULT_REPLAY) {
            $cached = $claim['cached'];
            \assert(\is_array($cached));
            $decoded = json_decode($cached['body'], true);

            return \is_array($decoded) ? $decoded : [];
        }

        try {
            $structured = $this->dispatcher->callTool($name, $arguments, $context);
            if ($idempotencyKey !== null && $this->isMutatingTool($name)) {
                $this->idempotencyStore->commit(
                    $context->config->getSalesChannelId(),
                    $idempotencyKey,
                    new Response(json_encode($structured, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES), 200, ['content-type' => 'application/json'])
                );
            }
        } catch (\Throwable $e) {
            if ($idempotencyKey !== null && $this->isMutatingTool($name)) {
                $this->idempotencyStore->abort($context->config->getSalesChannelId(), $idempotencyKey);
            }

            throw $e;
        }

        // MCP dual-output: structuredContent + content[]
        return [
            'structuredContent' => $structured,
            'content' => [
                ['type' => 'text', 'text' => json_encode($structured, \JSON_UNESCAPED_SLASHES) ?: ''],
            ],
        ];
    }

    private function getContext(Request $request): UcpRequestContext
    {
        $context = $request->attributes->get(UcpRequestContext::REQUEST_ATTRIBUTE);
        if (!$context instanceof UcpRequestContext) {
            throw UcpException::featureDisabled();
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveIdempotencyKey(array $params, Request $request): ?string
    {
        $header = $request->headers->get('idempotency-key');
        if (\is_string($header) && $header !== '') {
            return $header;
        }

        $meta = $params['_meta'] ?? [];
        if (!\is_array($meta)) {
            return null;
        }

        foreach (['idempotency_key', 'idempotencyKey'] as $field) {
            $value = $meta[$field] ?? null;
            if (\is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{status: string, cached: array{status:int, headers: array<string,string>, body: string}|null}|null
     */
    private function claimIdempotency(string $toolName, array $arguments, UcpRequestContext $context, ?string $idempotencyKey): ?array
    {
        if (!$this->isMutatingTool($toolName)) {
            return null;
        }

        if ($idempotencyKey === null) {
            if ($context->config->isIdempotencyRequired()) {
                throw UcpException::idempotencyKeyRequired();
            }

            return null;
        }

        $routeName = 'mcp.' . $toolName;
        $fingerprint = IdempotencyStore::computeFingerprint(
            $routeName,
            'POST',
            '/ucp/mcp',
            '',
            json_encode($arguments, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES)
        );

        $claim = $this->idempotencyStore->claim(
            $context->config->getSalesChannelId(),
            $idempotencyKey,
            $routeName,
            $fingerprint
        );

        if ($claim['status'] === IdempotencyStore::RESULT_IN_FLIGHT) {
            throw UcpException::idempotencyKeyConflict($idempotencyKey);
        }

        return $claim;
    }

    private function isMutatingTool(string $toolName): bool
    {
        return \in_array($toolName, [
            'create_cart',
            'update_cart',
            'cancel_cart',
            'create_checkout',
            'update_checkout',
            'complete_checkout',
            'cancel_checkout',
        ], true);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function buildErrorObject(mixed $id, int $code, string $message, array $data = []): array
    {
        $error = ['code' => $code, 'message' => $message];
        if ($data !== []) {
            $error['data'] = $data;
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => $error];
    }

    private function jsonRpcError(mixed $id, int $code, string $message): JsonResponse
    {
        return new JsonResponse($this->buildErrorObject($id, $code, $message));
    }
}
