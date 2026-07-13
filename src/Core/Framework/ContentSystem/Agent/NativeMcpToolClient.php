<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Agent;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
final class NativeMcpToolClient
{
    private ?string $sessionId = null;

    public function __construct(
        private readonly HttpKernelInterface $kernel,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function resetSession(): void
    {
        $this->sessionId = null;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function call(string $name, array $arguments): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $authorization = $request?->headers->get('Authorization');
        $headers = ['Content-Type' => 'application/json'];
        if ($authorization !== null) {
            $headers['Authorization'] = $authorization;
        }

        if ($this->sessionId === null) {
            $initialization = $this->request([
                    'jsonrpc' => '2.0',
                    'id' => 0,
                    'method' => 'initialize',
                    'params' => [
                        'protocolVersion' => '2025-03-26',
                        'capabilities' => new \stdClass(),
                        'clientInfo' => ['name' => 'Shopware Experience Studio', 'version' => '1.0.0'],
                    ],
                ], $headers);
            $sessionId = $initialization->headers->get('mcp-session-id');
            if (!\is_string($sessionId)) {
                return json_encode(['success' => false, 'error' => 'Could not establish an MCP session.'], \JSON_THROW_ON_ERROR);
            }

            $this->sessionId = $sessionId;
        }

        $headers['Mcp-Session-Id'] = $this->sessionId;
        $response = $this->request([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => ['name' => $name, 'arguments' => $arguments],
            ], $headers);
        $body = json_decode((string) $response->getContent(), true);

        $content = $body['result']['content'][0]['text'] ?? null;
        if (\is_string($content)) {
            $decodedContent = json_decode($content, true);
            $isUnhandledToolError = $content === 'Error while executing tool'
                || (
                    \is_array($decodedContent)
                    && ($decodedContent['success'] ?? null) === false
                    && ($decodedContent['error'] ?? null) === 'Error while executing tool'
                );
            if ($isUnhandledToolError) {
                $error = \sprintf(
                    'MCP tool "%s" failed with an unhandled exception. HTTP %d. Arguments: %s',
                    $name,
                    $response->getStatusCode(),
                    json_encode($this->diagnosticArguments($arguments), \JSON_THROW_ON_ERROR),
                );

                return json_encode(['success' => false, 'error' => $error], \JSON_THROW_ON_ERROR);
            }

            return $content;
        }

        $error = $body['error']['message'] ?? 'MCP tool call failed.';
        if (isset($body['error']['data'])) {
            $error .= ' Data: ' . json_encode($body['error']['data'], \JSON_THROW_ON_ERROR);
        }

        return json_encode([
            'success' => false,
            'error' => \sprintf(
                'MCP tool "%s" failed. HTTP %d. %s Arguments: %s',
                $name,
                $response->getStatusCode(),
                $error,
                json_encode($this->diagnosticArguments($arguments), \JSON_THROW_ON_ERROR),
            ),
        ], \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    private function request(array $payload, array $headers): \Symfony\Component\HttpFoundation\Response
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $request = Request::create('/api/_mcp', Request::METHOD_POST, [], [], [], $server, json_encode($payload, \JSON_THROW_ON_ERROR));

        return $this->kernel->handle($request, HttpKernelInterface::SUB_REQUEST);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function diagnosticArguments(array $arguments): array
    {
        foreach ($arguments as $name => $value) {
            if ($name === 'layout' && \is_string($value)) {
                $arguments[$name] = $this->summarizeLayout($value);

                continue;
            }

            if ($name !== 'request' || !\is_string($value)) {
                continue;
            }

            $request = json_decode($value, true);
            if (!\is_array($request)) {
                continue;
            }

            if (\is_array($request['layout'] ?? null)) {
                $encodedLayout = json_encode($request['layout'], \JSON_THROW_ON_ERROR);
                $request['layout'] = $this->summarizeLayout($encodedLayout);
            }

            $arguments[$name] = $request;
        }

        return $arguments;
    }

    /**
     * @return array{bytes: int, topLevelElements: int|null, sha256: string}
     */
    private function summarizeLayout(string $layout): array
    {
        $decodedLayout = json_decode($layout, true);

        return [
            'bytes' => \strlen($layout),
            'topLevelElements' => \is_array($decodedLayout) ? \count($decodedLayout) : null,
            'sha256' => hash('sha256', $layout),
        ];
    }
}
