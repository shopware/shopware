<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Http;

use Mcp\Schema\JsonRpc\Error;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Answers a request for a session the caller may not use with the same `404` the SDK sends for an
 * unknown session. It runs inside the transport's default middleware, so the answer carries the same
 * CORS headers as every other MCP response.
 */
#[Package('framework')]
final readonly class McpSessionNotFoundMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->responseFactory->createResponse(404)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode(Error::forInvalidRequest('Session not found or has expired.'), \JSON_THROW_ON_ERROR)));
    }
}
