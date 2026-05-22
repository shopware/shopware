<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use Mcp\Server\Session\FileSessionStore;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Regression guard for MCP session persistence.
 *
 * Shopware's `mcp.yaml` intentionally omits `session.store` so the bundle's default (`file` →
 * `FileSessionStore` at `%kernel.cache_dir%/mcp-sessions/`) applies. The alternatives would
 * break the stateless Admin API: `framework` requires `session_start()`, and `cache` falls back
 * to the `ArrayAdapter` in dev (sessions evaporate). If a bundle update ever changes the default
 * away from `file`, or renames the `mcp.session.store` service, these tests fail loudly.
 *
 * Production deployments that need shared session storage across workers override
 * `mcp.session.store` in `config/services.yaml` — see `docs/setup.md`.
 *
 * @internal
 */
#[Package('framework')]
class McpSessionPersistenceTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;

    public function testSessionStoreServiceIsFileSessionStore(): void
    {
        $container = static::getContainer();
        static::assertTrue($container->has('mcp.session.store'), 'mcp.session.store is not registered');

        $store = $container->get('mcp.session.store');
        static::assertInstanceOf(
            FileSessionStore::class,
            $store,
            'mcp.session.store must be a FileSessionStore by default. If a bundle update changed '
            . 'the default store, either override it back to `file` in mcp.yaml or register a '
            . 'store that works with the stateless Admin API (see docs/setup.md).',
        );
    }

    public function testInitializeReturnsSessionIdHeader(): void
    {
        Feature::skipTestIfInActive('MCP_SERVER', $this);
        $browser = $this->getBrowser();
        $this->initialize($browser);

        static::assertSame(
            200,
            $browser->getResponse()->getStatusCode(),
            'MCP initialize returned non-200: ' . (string) $browser->getResponse()->getContent(),
        );

        $sessionId = $this->extractSessionId($browser->getResponse()->headers->all());
        static::assertNotNull($sessionId, 'initialize did not return an Mcp-Session-Id header');
        static::assertNotSame('', $sessionId);
    }

    public function testSessionPersistsAcrossRequests(): void
    {
        Feature::skipTestIfInActive('MCP_SERVER', $this);
        $browser = $this->getBrowser();
        $this->initialize($browser);

        $sessionId = $this->extractSessionId($browser->getResponse()->headers->all());
        static::assertNotNull($sessionId);

        $browser->request(
            'POST',
            '/api/_mcp',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_MCP_SESSION_ID' => $sessionId,
            ],
            json_encode([
                'jsonrpc' => '2.0',
                'method' => 'tools/list',
                'params' => new \stdClass(),
                'id' => 2,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $browser->getResponse();
        static::assertSame(
            200,
            $response->getStatusCode(),
            'Second request with a valid session id failed — session did not persist. Body: '
            . (string) $response->getContent(),
        );

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertArrayNotHasKey(
            'error',
            $body,
            'tools/list after initialize returned an error — session was lost between requests: '
            . json_encode($body),
        );
        static::assertArrayHasKey('result', $body);
    }

    public function testRequestWithUnknownSessionIdIsRejected(): void
    {
        Feature::skipTestIfInActive('MCP_SERVER', $this);
        $browser = $this->getBrowser();
        $browser->request(
            'POST',
            '/api/_mcp',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_MCP_SESSION_ID' => '00000000-0000-4000-8000-000000000000',
            ],
            json_encode([
                'jsonrpc' => '2.0',
                'method' => 'tools/list',
                'params' => new \stdClass(),
                'id' => 1,
            ], \JSON_THROW_ON_ERROR),
        );

        static::assertSame(404, $browser->getResponse()->getStatusCode());
    }

    private function initialize(KernelBrowser $browser): void
    {
        $browser->request(
            'POST',
            '/api/_mcp',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'jsonrpc' => '2.0',
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 'mcp-session-persistence-test', 'version' => '1.0'],
                ],
                'id' => 1,
            ], \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array<string, list<string|null>> $headers
     */
    private function extractSessionId(array $headers): ?string
    {
        $sessionHeader = $headers['mcp-session-id'] ?? $headers['Mcp-Session-Id'] ?? null;
        $value = $sessionHeader[0] ?? null;

        return \is_string($value) ? $value : null;
    }
}
