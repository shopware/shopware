<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\DualEra;

use Mcp\Schema\Wire\McpHeader;
use Mcp\Server\Stateless\RequestMeta;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * A minimal MCP client speaking one era. A stateless request is built from scratch rather than adapted from
 * a handshake one: it needs the `_meta` envelope `RequestMeta::fromParams()` requires, and the `Mcp-Method`
 * and `Mcp-Name` headers the stateless leg checks against the body.
 *
 * @internal
 */
#[Package('framework')]
final class McpEraClient
{
    private ?string $sessionId = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $initializeResult = null;

    /**
     * @var list<string>
     */
    private array $lastNotifications = [];

    private int $id = 0;

    public function __construct(
        private readonly KernelBrowser $browser,
        private readonly string $uri,
        private readonly McpEra $era,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function result(string $method, array $params = []): array
    {
        $this->post($method, $params);
        // an event stream is a StreamedResponse without content; the browser keeps what it sent
        $content = $this->browser->getInternalResponse()->getContent();

        $this->lastNotifications = [];
        $body = null;
        foreach (self::messages($content) as $message) {
            if (\array_key_exists('id', $message)) {
                $body = $message;
            } else {
                $this->lastNotifications[] = (string) ($message['method'] ?? '');
            }
        }

        TestCase::assertIsArray($body['result'] ?? null, \sprintf('%s %s failed: %s', $this->era->name, $method, $content));

        return $body['result'];
    }

    /**
     * The methods of the notifications delivered together with the last result.
     *
     * @return list<string>
     */
    public function lastNotifications(): array
    {
        return $this->lastNotifications;
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function callTool(string $name, array $arguments = []): array
    {
        return $this->result('tools/call', ['name' => $name, 'arguments' => $arguments === [] ? new \stdClass() : $arguments]);
    }

    /**
     * @return list<string>
     */
    public function toolNames(): array
    {
        $tools = $this->result('tools/list')['tools'] ?? [];
        TestCase::assertIsArray($tools);

        return array_values(array_map(static fn (array $tool): string => $tool['name'], $tools));
    }

    /**
     * @param array<string, mixed> $params
     */
    public function post(string $method, array $params = []): Response
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_MCP_PROTOCOL_VERSION' => $this->era->protocolVersion()->value,
        ];

        if ($this->era === McpEra::Handshake) {
            $server['HTTP_MCP_SESSION_ID'] = $this->sessionId();
        } else {
            $params['_meta'] = [
                RequestMeta::PROTOCOL_VERSION => $this->era->protocolVersion()->value,
                RequestMeta::CLIENT_CAPABILITIES => new \stdClass(),
                RequestMeta::CLIENT_INFO => ['name' => 'mcp-dual-era-conformance-test', 'version' => '1.0'],
            ];
            $server['HTTP_MCP_METHOD'] = $method;

            $name = McpHeader::nameFor($method, $params);
            if ($name !== null) {
                $server['HTTP_MCP_NAME'] = (string) McpHeader::encode($name);
            }
        }

        return $this->send('POST', $server, [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params === [] ? new \stdClass() : $params,
            'id' => ++$this->id,
        ]);
    }

    public function delete(): Response
    {
        $server = $this->era === McpEra::Handshake
            ? ['HTTP_MCP_SESSION_ID' => $this->sessionId()]
            : ['HTTP_MCP_PROTOCOL_VERSION' => McpEra::Stateless->protocolVersion()->value];

        return $this->send('DELETE', $server);
    }

    public function sessionId(): string
    {
        if ($this->sessionId === null) {
            $this->initialize();
        }
        \assert($this->sessionId !== null);

        return $this->sessionId;
    }

    /**
     * @return array<string, mixed>
     */
    public function initializeResult(): array
    {
        if ($this->initializeResult === null) {
            $this->initialize();
        }
        \assert($this->initializeResult !== null);

        return $this->initializeResult;
    }

    public function lastSessionIdHeader(): ?string
    {
        return $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_MCP_SESSION_ID);
    }

    /**
     * The text of every text block of a tool result.
     *
     * @param array<string, mixed> $result
     */
    public static function text(array $result): string
    {
        $text = '';
        foreach ($result['content'] ?? [] as $block) {
            if (\is_array($block) && ($block['type'] ?? null) === 'text') {
                $text .= $block['text'] . "\n";
            }
        }

        return $text;
    }

    /**
     * A response is one JSON message, or an event stream when queued notifications go out with it.
     *
     * @return list<array<string, mixed>>
     */
    private static function messages(string $content): array
    {
        $payloads = [$content];
        if (!str_starts_with(ltrim($content), '{')) {
            preg_match_all('/^data: ?(.*)$/m', $content, $matches);
            $payloads = $matches[1];
        }

        $messages = [];
        foreach ($payloads as $payload) {
            $message = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
            TestCase::assertIsArray($message, $content);
            $messages[] = $message;
        }

        return $messages;
    }

    private function initialize(): void
    {
        TestCase::assertSame(McpEra::Handshake, $this->era, 'the stateless era has no initialize');

        $response = $this->send('POST', ['CONTENT_TYPE' => 'application/json'], [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => McpEra::Handshake->protocolVersion()->value,
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'mcp-dual-era-conformance-test', 'version' => '1.0'],
            ],
            'id' => ++$this->id,
        ]);

        $sessionId = $response->headers->get(PlatformRequest::HEADER_MCP_SESSION_ID);
        TestCase::assertIsString($sessionId, 'initialize did not return an Mcp-Session-Id: ' . $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        TestCase::assertIsArray($body['result'] ?? null, (string) $response->getContent());

        $this->sessionId = $sessionId;
        $this->initializeResult = $body['result'];

        $this->send('POST', ['CONTENT_TYPE' => 'application/json', 'HTTP_MCP_SESSION_ID' => $sessionId], [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]);
    }

    /**
     * @param array<string, string> $server
     * @param array<string, mixed>|null $body
     */
    private function send(string $method, array $server, ?array $body = null): Response
    {
        $this->browser->request(
            $method,
            $this->uri,
            [],
            [],
            $server,
            $body !== null ? json_encode($body, \JSON_THROW_ON_ERROR) : null,
        );

        return $this->browser->getResponse();
    }
}
