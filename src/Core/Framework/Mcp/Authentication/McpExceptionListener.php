<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Authentication;

use Mcp\Schema\JsonRpc\Error;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Converts exceptions thrown on the MCP endpoint into JSON-RPC error responses,
 * so MCP clients receive a parseable error instead of an HTML page.
 *
 * Without this, a 401 from a wrong access key causes some clients to fall back
 * to non-standard endpoints (e.g. POST /register), making the real error invisible.
 */
#[Package('framework')]
class McpExceptionListener implements EventSubscriberInterface
{
    private const MCP_ROUTE_NAME = 'api.mcp.endpoint';

    // Must run before Symfony's default exception listener (priority 0) so we intercept before an HTML error page is rendered.
    private const PRIORITY = 10;

    // Not covered by the MCP SDK's Error constants — defined here for clarity.
    private const CODE_UNAUTHORIZED = -32001;
    private const CODE_RATE_LIMITED = -32029;

    /**
     * Some MCP clients (e.g. Cursor) fall back to POST {origin}/register when the primary
     * connection fails, expecting a JSON OAuth error response. Without this, they get a
     * Symfony HTML 404 (or a redirect to a customer-register storefront page) which they
     * cannot parse, hiding the real error. POST is used as the gate because browser
     * navigation to a register page is GET, so POST + /register is a strong signal of an
     * OAuth client registration attempt.
     */
    private const OAUTH_FALLBACK_PATH = '/register';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onException', self::PRIORITY],
        ];
    }

    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->attributes->get('_route') === self::MCP_ROUTE_NAME) {
            $this->handleMcpException($event);

            return;
        }

        if ($request->getPathInfo() === self::OAUTH_FALLBACK_PATH && $request->getMethod() === 'POST') {
            $event->setResponse(new JsonResponse([
                'error' => 'invalid_client',
                'error_description' => 'Authentication failed. Configure your MCP client with the correct sw-access-key and sw-secret-access-key from your Shopware integration (Settings → Integrations). The MCP endpoint is /api/_mcp.',
            ], Response::HTTP_UNAUTHORIZED));

            return;
        }
    }

    private function handleMcpException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $httpCode = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;

        $error = new Error(
            id: '',
            code: $this->toJsonRpcCode($httpCode),
            message: $exception->getMessage(),
        );

        $event->setResponse(new JsonResponse($error->jsonSerialize(), $httpCode));
    }

    private function toJsonRpcCode(int $httpCode): int
    {
        return match (true) {
            $httpCode === Response::HTTP_UNAUTHORIZED, $httpCode === Response::HTTP_FORBIDDEN => self::CODE_UNAUTHORIZED,
            $httpCode === Response::HTTP_TOO_MANY_REQUESTS => self::CODE_RATE_LIMITED,
            $httpCode >= Response::HTTP_BAD_REQUEST && $httpCode < Response::HTTP_INTERNAL_SERVER_ERROR => Error::INVALID_REQUEST,
            default => Error::SERVER_ERROR,
        };
    }
}
