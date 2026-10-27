<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Tool;
use Mcp\Schema\Wire\McpHeader;
use Mcp\Server;
use Mcp\Server\Builder;
use Mcp\Server\Stateless\RequestMeta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistFilter;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\Controller\StoreApiMcpServerController;
use Shopware\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopware\Core\Framework\Mcp\Resource\McpResourceCacheHint;
use Shopware\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the same scenarios over both MCP protocol eras on both endpoints, and asserts the behaviour agreed
 * in adr/2026-09-24-mcp-stateless-era-degradation-policy.md.
 *
 * Both servers stay on the handshake era unless `shopware.mcp.protocol_era` says `auto`, which the shared
 * test kernel does not. This test therefore boots a kernel of its own, builds a server with both legs from
 * the same builder and swaps it into both controllers of that kernel. The shared test kernel stays untouched.
 *
 * @internal
 */
#[Package('framework')]
class McpDualEraConformanceTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const ADMIN = '/api/_mcp';
    private const STORE = '/store-api/_mcp';

    private const DISCOVERY_TOOLS = ['shopware-tool-search', 'shopware-toolsets-list', 'shopware-toolset-enable'];

    /**
     * A kernel of its own, because a controller the shared kernel already created cannot be replaced.
     */
    private static ?Kernel $dualEraKernel = null;

    public static function tearDownAfterClass(): void
    {
        self::$dualEraKernel?->shutdown();
        self::$dualEraKernel = null;
    }

    /**
     * Booted lazily here rather than in setUpBeforeClass(), so a deprecation during the boot is recorded
     * for a test instead of aborting the run.
     */
    protected function setUp(): void
    {
        if (self::$dualEraKernel !== null) {
            return;
        }

        $kernel = KernelLifecycleManager::createKernel();
        $kernel->boot();

        $container = $kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);
        DualEraServerFixture::install($container);

        self::$dualEraKernel = $kernel;
    }

    /**
     * @return iterable<string, array{string, McpEra}>
     */
    public static function endpointAndEraProvider(): iterable
    {
        yield 'Admin API endpoint, handshake era' => [self::ADMIN, McpEra::Handshake];
        yield 'Admin API endpoint, stateless era' => [self::ADMIN, McpEra::Stateless];
        yield 'Store API endpoint, handshake era' => [self::STORE, McpEra::Handshake];
        yield 'Store API endpoint, stateless era' => [self::STORE, McpEra::Stateless];
    }

    /**
     * @return iterable<string, array{McpEra}>
     */
    public static function eraProvider(): iterable
    {
        yield 'handshake era' => [McpEra::Handshake];
        yield 'stateless era' => [McpEra::Stateless];
    }

    /**
     * Guards every other scenario: a stateless request must really be answered by the stateless leg, not
     * fall through to the handshake leg, or the tests below would prove nothing about that era.
     */
    #[DataProvider('endpointAndEraProvider')]
    public function testEachEraIsAnsweredByItsOwnLeg(string $endpoint, McpEra $era): void
    {
        $client = $this->client($endpoint, $era);

        $client->result('tools/list');

        if ($era === McpEra::Handshake) {
            static::assertNotSame('', $client->sessionId(), 'the handshake leg mints an Mcp-Session-Id');
            static::assertArrayNotHasKey('resultType', $client->lastResponse()['result']);

            return;
        }

        static::assertNull($client->lastSessionIdHeader(), 'the stateless leg must not mint an Mcp-Session-Id');
        static::assertSame('complete', $client->lastResponse()['result']['resultType'] ?? null, 'only the 2026-07-28 codec stamps resultType');
    }

    /**
     * The modern era has no `initialize`. A client learns what the server offers through `server/discover`.
     */
    #[DataProvider('endpointAndEraProvider')]
    public function testCapabilitiesAreDiscoverable(string $endpoint, McpEra $era): void
    {
        if ($era === McpEra::Handshake) {
            $capabilities = $this->client($endpoint, $era)->initializeResult()['capabilities'] ?? null;
        } else {
            $discover = $this->client($endpoint, $era)->result('server/discover');
            $capabilities = $discover['capabilities'] ?? null;
            static::assertContains('2026-07-28', $discover['supportedVersions'] ?? [], (string) json_encode($discover));
        }

        static::assertIsArray($capabilities);
        static::assertArrayHasKey('tools', $capabilities);
    }

    /**
     * Without `?toolsets=` only the discovery meta-tools are advertised. The stateless era has no session to
     * enable a toolset in, so it advertises `shopware-tool-search` alone.
     */
    #[DataProvider('endpointAndEraProvider')]
    public function testToolsListWithoutToolsetsAdvertisesOnlyDiscoveryTools(string $endpoint, McpEra $era): void
    {
        $tools = $this->client($endpoint, $era)->toolNames();

        if ($era === McpEra::Handshake) {
            static::assertEqualsCanonicalizing(self::DISCOVERY_TOOLS, $tools);

            return;
        }

        static::assertSame(['shopware-tool-search'], $tools);
    }

    /**
     * `?toolsets=` is the way to choose tools that works on both eras: the toolset is advertised from the
     * first `tools/list`, without any earlier request.
     */
    #[DataProvider('endpointAndEraProvider')]
    public function testToolsetsInTheUrlAreAdvertisedFromTheFirstToolsList(string $endpoint, McpEra $era): void
    {
        [$toolset, $tool] = self::toolsetOf($endpoint);

        $tools = $this->client($endpoint, $era, '?toolsets=' . $toolset)->toolNames();

        static::assertContains($tool, $tools);
        static::assertContains('shopware-tool-search', $tools);
    }

    /**
     * SEP-2549 cache hints exist on 2026-07-28 only. A list differs per principal and per `?toolsets=`, so it
     * is `private`, with a short TTL as the fallback for clients that do not follow list changes.
     */
    #[DataProvider('endpointAndEraProvider')]
    public function testListsCarryPrivateCacheHintsOnTheStatelessEraOnly(string $endpoint, McpEra $era): void
    {
        [$toolset] = self::toolsetOf($endpoint);

        foreach (['', '?toolsets=' . $toolset] as $query) {
            $result = $this->client($endpoint, $era, $query)->result('tools/list');

            if ($era === McpEra::Handshake) {
                static::assertArrayNotHasKey('ttlMs', $result, $query);
                static::assertArrayNotHasKey('cacheScope', $result, $query);

                continue;
            }

            static::assertSame(60_000, $result['ttlMs'] ?? null, $query);
            static::assertSame('private', $result['cacheScope'] ?? null, $query);
        }

        if ($era === McpEra::Stateless) {
            $discover = $this->client($endpoint, $era)->result('server/discover');
            static::assertSame(60_000, $discover['ttlMs'] ?? null);
            static::assertSame('private', $discover['cacheScope'] ?? null);
        }
    }

    /**
     * @return iterable<string, array{McpEra, string, int}>
     */
    public static function referenceResourceProvider(): iterable
    {
        foreach (McpEra::cases() as $era) {
            yield 'shopware://entities, ' . $era->name => [$era, 'shopware://entities', McpResourceCacheHint::DEPLOYMENT_TTL_MS];
            yield 'shopware://currencies, ' . $era->name => [$era, 'shopware://currencies', McpResourceCacheHint::SHOP_CONFIGURATION_TTL_MS];
        }
    }

    /**
     * The reference resources rarely change and name their own TTL on 2026-07-28. The handshake answer
     * stays as it was.
     */
    #[DataProvider('referenceResourceProvider')]
    public function testReferenceResourcesCarryTheirOwnTtlOnTheStatelessEraOnly(McpEra $era, string $uri, int $ttlMs): void
    {
        $read = $this->client(self::ADMIN, $era)->result('resources/read', ['uri' => $uri]);

        static::assertSame($uri, $read['contents'][0]['uri'] ?? null, (string) json_encode($read));

        if ($era === McpEra::Handshake) {
            static::assertArrayNotHasKey('ttlMs', $read);
            static::assertArrayNotHasKey('cacheScope', $read);

            return;
        }

        static::assertSame($ttlMs, $read['ttlMs'] ?? null);
        static::assertSame('private', $read['cacheScope'] ?? null);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function endpointProvider(): iterable
    {
        yield 'Admin API endpoint' => [self::ADMIN];
        yield 'Store API endpoint' => [self::STORE];
    }

    /**
     * Regression baseline: on the handshake era, enabling a toolset advertises it on the next
     * `tools/list` of the same session, which also delivers the queued `list_changed`.
     */
    #[DataProvider('endpointProvider')]
    public function testToolsetEnableAdvertisesTheToolsetOnTheHandshakeEra(string $endpoint): void
    {
        [$toolset, $tool] = self::toolsetOf($endpoint);
        $client = $this->client($endpoint, McpEra::Handshake);

        $result = $client->callTool('shopware-toolset-enable', ['toolset' => $toolset]);

        static::assertNotTrue($result['isError'] ?? false, (string) json_encode($result));
        static::assertContains($tool, $client->toolNames());
        static::assertContains('notifications/tools/list_changed', $client->lastNotifications());
    }

    /**
     * The stateless era has no session to keep an enabled toolset in, so neither toolset session tool is
     * advertised there. A client that kept a name from a handshake connection gets an `isError` that points
     * to `?toolsets=` and tool search, never a success while the next `tools/list` stays unchanged.
     */
    #[DataProvider('endpointProvider')]
    public function testToolsetSessionToolsAnswerAnErrorOnTheStatelessEra(string $endpoint): void
    {
        [$toolset, $tool] = self::toolsetOf($endpoint);
        $client = $this->client($endpoint, McpEra::Stateless);

        foreach (['shopware-toolset-enable' => ['toolset' => $toolset], 'shopware-toolsets-list' => []] as $name => $arguments) {
            $result = $client->callTool($name, $arguments);

            static::assertTrue($result['isError'] ?? false, $name . ' must answer with an error without a session: ' . json_encode($result));
            static::assertStringContainsString('?toolsets=', McpEraClient::text($result), $name);
            static::assertStringContainsString('shopware-tool-search', McpEraClient::text($result), $name);
        }

        static::assertNotContains($tool, $client->toolNames());
    }

    /**
     * The server instructions of the stateless era point to `?toolsets=` and tool search, not to the toolset
     * session tools it does not offer. The handshake era keeps its instructions.
     */
    #[DataProvider('endpointAndEraProvider')]
    public function testServerInstructionsOnlyNameToolsTheEraOffers(string $endpoint, McpEra $era): void
    {
        $client = $this->client($endpoint, $era);
        $instructions = $era === McpEra::Handshake
            ? $client->initializeResult()['instructions'] ?? null
            : $client->result('server/discover')['instructions'] ?? null;

        static::assertIsString($instructions);
        static::assertStringContainsString('shopware-tool-search', $instructions);

        if ($era === McpEra::Handshake) {
            static::assertStringContainsString('shopware-toolset-enable', $instructions);

            return;
        }

        static::assertStringContainsString('?toolsets=', $instructions);
        static::assertStringNotContainsString('shopware-toolset-enable', $instructions);
        static::assertStringNotContainsString('shopware-toolsets-list', $instructions);
    }

    /**
     * The `shopware-context` prompt of the Admin API endpoint guides the model through discovery, so on the
     * stateless era it must not send it to the toolset session tools that era does not offer.
     */
    #[DataProvider('eraProvider')]
    public function testContextPromptOnlyNamesToolsTheEraOffers(McpEra $era): void
    {
        $messages = $this->client(self::ADMIN, $era)->result('prompts/get', ['name' => 'shopware-context'])['messages'] ?? null;
        static::assertIsArray($messages);
        $text = (string) json_encode($messages);

        static::assertStringContainsString('shopware-tool-search', $text);

        if ($era === McpEra::Handshake) {
            static::assertStringContainsString('shopware-toolset-enable', $text);

            return;
        }

        static::assertStringContainsString('?toolsets=', $text);
        static::assertStringNotContainsString('shopware-toolset-enable', $text);
        static::assertStringNotContainsString('shopware-toolsets-list', $text);
    }

    /**
     * On the stateless era tool search is the way to find a tool, so it names the toolset of every match for
     * `?toolsets=` and does not lead to the toolset session tools.
     */
    #[DataProvider('endpointProvider')]
    public function testToolSearchPointsToToolsetsInTheUrlOnTheStatelessEra(string $endpoint): void
    {
        [$toolset, $tool] = self::toolsetOf($endpoint);
        $client = $this->client($endpoint, McpEra::Stateless);

        $structured = $client->callTool('shopware-tool-search', ['query' => $tool, 'maxResults' => 20])['structuredContent'] ?? null;
        static::assertIsArray($structured);
        static::assertIsString($structured['_meta']['usage'] ?? null);

        $toolsets = [];
        foreach ($structured['result'] ?? [] as $match) {
            static::assertIsArray($match);
            $toolsets[$match['tool']['name']] = $match['toolset'] ?? null;
        }

        static::assertSame($toolset, $toolsets[$tool] ?? null, (string) json_encode($structured));
        static::assertStringContainsString('?toolsets=', $structured['_meta']['usage']);
        static::assertStringNotContainsString('shopware-toolset-enable', $structured['_meta']['usage']);

        $hidden = $client->callTool('shopware-tool-search', ['query' => 'toolset enable list', 'maxResults' => 20])['structuredContent']['result'] ?? [];
        static::assertIsArray($hidden);
        static::assertSame([], array_values(array_intersect(
            array_map(static fn (array $match): string => $match['tool']['name'], $hidden),
            ['shopware-toolset-enable', 'shopware-toolsets-list'],
        )));
    }

    /**
     * A result above 100 KB is stored and linked with a `resource_link` whose pointer is signed for the
     * principal. Any later request of that principal reads it, on either era.
     */
    #[DataProvider('eraProvider')]
    public function testAnOversizedAdminResultIsLinkedAndReadableByTheSamePrincipal(McpEra $era): void
    {
        $browser = $this->adminBrowser();

        $result = $this->client(self::ADMIN, $era, browser: $browser)->callTool('shopware-entity-search', [
            'entity' => 'country',
            'criteria' => '{"associations":{"states":{}}}',
            'limit' => 500,
        ]);

        $uri = self::resourceLinkUri($result);

        foreach (McpEra::cases() as $readingEra) {
            $read = $this->client(self::ADMIN, $readingEra, browser: $browser)->result('resources/read', ['uri' => $uri]);
            $stored = json_decode($read['contents'][0]['text'] ?? '', true, 512, \JSON_THROW_ON_ERROR);

            // both eras negotiate a revision since 2025-06-18, which stores the bare result data
            static::assertIsList($stored, $readingEra->name . ': ' . json_encode($read));
            static::assertNotSame([], $stored);
        }
    }

    /**
     * On the Store API the principal is the sales channel plus the `sw-context-token`.
     */
    #[DataProvider('eraProvider')]
    public function testAnOversizedStoreResultIsLinkedAndReadableWithTheSameContextToken(McpEra $era): void
    {
        $browser = $this->storeBrowser();

        $result = $this->client(self::STORE, $era, browser: $browser)->callTool(OversizedResultTool::NAME);

        $uri = self::resourceLinkUri($result);

        foreach (McpEra::cases() as $readingEra) {
            $read = $this->client(self::STORE, $readingEra, browser: $browser)->result('resources/read', ['uri' => $uri]);
            $stored = json_decode($read['contents'][0]['text'] ?? '', true, 512, \JSON_THROW_ON_ERROR);

            static::assertSame(OversizedResultTool::rows(), $stored, $readingEra->name);
        }
    }

    /**
     * A Store API client without `sw-context-token` gets a token minted for this one request, which no
     * later request could present. Its result is therefore returned inline.
     */
    #[DataProvider('eraProvider')]
    public function testAStoreClientWithoutContextTokenGetsTheResultInline(McpEra $era): void
    {
        $browser = $this->storeBrowser();
        $browser->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_SW_ACCESS_KEY' => $browser->getServerParameter('HTTP_SW_ACCESS_KEY'),
        ]);

        $result = $this->client(self::STORE, $era, browser: $browser)->callTool(OversizedResultTool::NAME);

        static::assertNotTrue($result['isError'] ?? false, (string) json_encode($result));
        static::assertSame([], self::resourceLinks($result), 'nothing may be stored that no later request could read');
        static::assertStringContainsString(OversizedResultTool::rows()[999]['text'], McpEraClient::text($result) . json_encode($result['structuredContent'] ?? null));
    }

    /**
     * Handshake clients end their session with `DELETE`, which discards it. A stateless client never sends one.
     * A server that serves both eras answers a `DELETE` as the handshake revisions describe (2026-07-28,
     * "Earlier Streamable HTTP Revisions"); 405 is only for servers that serve 2026-07-28 alone. Without a
     * session id the handshake leg answers 400, as 2025-11-25 asks of servers that require a session.
     */
    #[DataProvider('endpointAndEraProvider')]
    public function testDelete(string $endpoint, McpEra $era): void
    {
        $client = $this->client($endpoint, $era);

        if ($era === McpEra::Handshake) {
            $client->result('tools/list');
            $response = $client->delete();

            // The specification names no success status; mcp/sdk 0.9 answers 200.
            static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
            static::assertSame(Response::HTTP_NOT_FOUND, $client->post('tools/list')->getStatusCode(), 'the session must be gone after DELETE');

            return;
        }

        $response = $client->delete();

        // Every DELETE goes to the handshake leg, which has no session to end and does not know 2026-07-28.
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * @return array{string, string} the toolset to select and one tool it advertises
     */
    private static function toolsetOf(string $endpoint): array
    {
        return $endpoint === self::ADMIN
            ? ['entity', 'shopware-entity-search']
            : ['store-api', 'shopware-store-api-context'];
    }

    /**
     * @param array<string, mixed> $result
     */
    private static function resourceLinkUri(array $result): string
    {
        $links = self::resourceLinks($result);
        static::assertCount(1, $links, 'expected exactly one resource_link: ' . json_encode($result));

        $uri = $links[0]['uri'] ?? null;
        static::assertIsString($uri);
        static::assertStringStartsWith('shopware://tool-result/', $uri);
        static::assertMatchesRegularExpression('#^shopware://tool-result/[^.]+\.\d+\.[^.]+$#', $uri, 'the pointer must be signed, not a plain session-scoped id');

        return $uri;
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return list<array<string, mixed>>
     */
    private static function resourceLinks(array $result): array
    {
        $content = $result['content'] ?? [];
        static::assertIsArray($content);

        return array_values(array_filter(
            $content,
            static fn (mixed $block): bool => \is_array($block) && ($block['type'] ?? null) === 'resource_link',
        ));
    }

    private function client(string $endpoint, McpEra $era, string $query = '', ?KernelBrowser $browser = null): McpEraClient
    {
        $browser ??= $endpoint === self::ADMIN ? $this->adminBrowser() : $this->storeBrowser();

        return new McpEraClient($browser, $endpoint . $query, $era);
    }

    private function adminBrowser(): KernelBrowser
    {
        \assert(self::$dualEraKernel !== null);

        return $this->createClient(self::$dualEraKernel);
    }

    private function storeBrowser(): KernelBrowser
    {
        \assert(self::$dualEraKernel !== null);

        return $this->createSalesChannelBrowser(self::$dualEraKernel);
    }
}

/**
 * @internal
 */
enum McpEra
{
    /**
     * Revisions up to 2025-11-25: `initialize`, then `Mcp-Session-Id` on every request.
     */
    case Handshake;

    /**
     * Revision 2026-07-28: no session, every request carries its protocol version and client
     * capabilities in `params._meta`.
     */
    case Stateless;
}

/**
 * A minimal MCP client speaking one era. A stateless request is built from scratch rather than adapted from
 * a handshake one: it needs the `_meta` envelope `RequestMeta::fromParams()` requires, and the SEP-2243
 * headers the stateless leg validates against the body.
 *
 * @internal
 */
final class McpEraClient
{
    private const HANDSHAKE_VERSION = ProtocolVersion::V2025_11_25;
    private const STATELESS_VERSION = ProtocolVersion::V2026_07_28;

    private ?string $sessionId = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $initializeResult = null;

    /**
     * @var array<string, mixed>
     */
    private array $lastResponse = [];

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
        $this->lastResponse = $body;

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
        $server = ['CONTENT_TYPE' => 'application/json'];

        if ($this->era === McpEra::Handshake) {
            $server['HTTP_MCP_SESSION_ID'] = $this->sessionId();
            $server['HTTP_MCP_PROTOCOL_VERSION'] = self::HANDSHAKE_VERSION->value;
        } else {
            $params['_meta'] = [
                RequestMeta::PROTOCOL_VERSION => self::STATELESS_VERSION->value,
                RequestMeta::CLIENT_CAPABILITIES => new \stdClass(),
                RequestMeta::CLIENT_INFO => ['name' => 'mcp-dual-era-conformance-test', 'version' => '1.0'],
            ];
            $server['HTTP_MCP_PROTOCOL_VERSION'] = self::STATELESS_VERSION->value;
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
            : ['HTTP_MCP_PROTOCOL_VERSION' => self::STATELESS_VERSION->value];

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
     * @return array<string, mixed>
     */
    public function lastResponse(): array
    {
        return $this->lastResponse;
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
                'protocolVersion' => self::HANDSHAKE_VERSION->value,
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

/**
 * Builds a server with both protocol legs from the builder the container configured, and puts it into
 * both MCP controllers. The container must not have created the controllers yet. The builder is put back
 * to the handshake-only pin right away, so nothing else built from it changes.
 *
 * @internal
 */
final class DualEraServerFixture
{
    public static function install(ContainerInterface $container): void
    {
        $container->set(McpServerController::class, new McpServerController(
            self::dualEraServer($container, 'mcp.server.admin.builder'),
            $container->get(McpHttpTransportFactory::class),
            $container->get(McpRateLimiter::class),
            $container->get(McpSessionIdValidator::class),
            $container->get(McpAllowlistProvider::class),
            $container->get(LoggerInterface::class),
            $container->get(McpAllowlistFilter::class),
            $container->get(McpListChangedNotifier::class),
        ));

        $storeNotifier = $container->get('mcp.store_api.list_changed_notifier');
        \assert($storeNotifier instanceof McpListChangedNotifier);

        $container->set(StoreApiMcpServerController::class, new StoreApiMcpServerController(
            self::dualEraServer($container, 'mcp.server.store_api.builder'),
            $container->get(McpHttpTransportFactory::class),
            $container->get(McpRateLimiter::class),
            $container->get(McpSessionIdValidator::class),
            $container->get(LoggerInterface::class),
            $storeNotifier,
        ));

        OversizedResultTool::register($container);
    }

    private static function dualEraServer(ContainerInterface $container, string $builderId): Server
    {
        $builder = $container->get($builderId);
        \assert($builder instanceof Builder);

        try {
            return $builder->setModernVersions([ProtocolVersion::V2026_07_28])->build();
        } finally {
            $builder->withoutModernEra();
        }
    }
}

/**
 * A Store API tool whose result is above the inline limit of {@see McpToolResponse}. No shipped Store API
 * tool returns that much, so the large-result path of that endpoint needs its own.
 *
 * @internal
 */
final class OversizedResultTool extends McpToolResponse
{
    public const NAME = 'dual-era-test-oversized-result';

    private const REGISTRY = 'mcp.server.store_api.registry';

    public function __invoke(): string
    {
        return $this->success(self::rows());
    }

    /**
     * @return list<array{id: int, text: string}>
     */
    public static function rows(): array
    {
        return array_map(
            static fn (int $id): array => ['id' => $id, 'text' => \sprintf('row-%04d-%s', $id, str_repeat('x', 100))],
            range(0, 999),
        );
    }

    public static function register(ContainerInterface $container): void
    {
        $tool = new self();

        $registry = $container->get(self::REGISTRY);
        \assert($registry instanceof RegistryInterface);
        $registry->getTools();
        $registry->registerTool(
            new Tool(self::NAME, null, ['type' => 'object', 'properties' => new \stdClass()], 'Returns a result above the inline limit.', null),
            $tool(...),
        );
    }
}
