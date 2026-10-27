<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Tests\Integration\Core\Framework\Mcp\DualEra\DualEraServerFixture;
use Shopware\Tests\Integration\Core\Framework\Mcp\DualEra\McpEra;
use Shopware\Tests\Integration\Core\Framework\Mcp\DualEra\McpEraClient;
use Shopware\Tests\Integration\Core\Framework\Mcp\DualEra\OversizedResultTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the same scenarios over both MCP protocol eras on both endpoints, and asserts the behaviour agreed
 * in adr/2026-09-24-mcp-stateless-era-degradation-policy.md.
 *
 * Both servers stay on the handshake era unless `shopware.mcp.protocol_era` says `auto`, which the shared
 * test kernel does not. This test therefore boots a kernel of its own, builds a server with both eras from
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
     * @return iterable<string, array{string}>
     */
    public static function endpointProvider(): iterable
    {
        yield 'Admin API endpoint' => [self::ADMIN];
        yield 'Store API endpoint' => [self::STORE];
    }

    /**
     * @return iterable<string, array{string, McpEra}>
     */
    public static function endpointAndEraProvider(): iterable
    {
        yield 'Admin API endpoint on the handshake era' => [self::ADMIN, McpEra::Handshake];
        yield 'Admin API endpoint on the stateless era' => [self::ADMIN, McpEra::Stateless];
        yield 'Store API endpoint on the handshake era' => [self::STORE, McpEra::Handshake];
        yield 'Store API endpoint on the stateless era' => [self::STORE, McpEra::Stateless];
    }

    /**
     * @return iterable<string, array{McpEra}>
     */
    public static function eraProvider(): iterable
    {
        yield 'handshake era' => [McpEra::Handshake];
        yield 'stateless era' => [McpEra::Stateless];
    }

    #[DataProvider('endpointProvider')]
    #[TestDox('The $_dataName answers a handshake request with a session')]
    public function testHandshakeRequestIsAnsweredWithASession(string $endpoint): void
    {
        $client = $this->client($endpoint, McpEra::Handshake);

        $result = $client->result('tools/list');

        static::assertNotSame('', $client->sessionId(), 'the handshake era mints an Mcp-Session-Id');
        static::assertArrayNotHasKey('resultType', $result, 'only the stateless era stamps resultType');
    }

    #[DataProvider('endpointProvider')]
    #[TestDox('The $_dataName answers a stateless request without a session, so the other scenarios really test that era')]
    public function testStatelessRequestIsAnsweredWithoutASession(string $endpoint): void
    {
        $client = $this->client($endpoint, McpEra::Stateless);

        $result = $client->result('tools/list');

        static::assertNull($client->lastSessionIdHeader(), 'the stateless era must not mint an Mcp-Session-Id');
        static::assertSame('complete', $result['resultType'] ?? null, 'only the stateless era stamps resultType');
    }

    #[DataProvider('endpointProvider')]
    #[TestDox('The $_dataName announces its capabilities in the initialize result of the handshake era')]
    public function testHandshakeCapabilitiesAreInTheInitializeResult(string $endpoint): void
    {
        $capabilities = $this->client($endpoint, McpEra::Handshake)->initializeResult()['capabilities'] ?? null;

        static::assertIsArray($capabilities);
        static::assertArrayHasKey('tools', $capabilities);
    }

    #[DataProvider('endpointProvider')]
    #[TestDox('The $_dataName announces its capabilities through server/discover on the stateless era, which has no initialize')]
    public function testStatelessCapabilitiesAreDiscoverable(string $endpoint): void
    {
        $discover = $this->client($endpoint, McpEra::Stateless)->result('server/discover');

        static::assertContains(McpEra::Stateless->protocolVersion()->value, $discover['supportedVersions'] ?? [], (string) json_encode($discover));
        static::assertIsArray($discover['capabilities'] ?? null);
        static::assertArrayHasKey('tools', $discover['capabilities']);
    }

    #[DataProvider('endpointProvider')]
    #[TestDox('Without ?toolsets= the $_dataName advertises only the discovery tools on the handshake era')]
    public function testHandshakeToolsListWithoutToolsetsAdvertisesOnlyDiscoveryTools(string $endpoint): void
    {
        $tools = $this->client($endpoint, McpEra::Handshake)->toolNames();

        static::assertEqualsCanonicalizing(self::DISCOVERY_TOOLS, $tools);
    }

    #[DataProvider('endpointProvider')]
    #[TestDox('Without ?toolsets= the $_dataName advertises only tool search on the stateless era, which has no session to enable a toolset in')]
    public function testStatelessToolsListWithoutToolsetsAdvertisesOnlyToolSearch(string $endpoint): void
    {
        $tools = $this->client($endpoint, McpEra::Stateless)->toolNames();

        static::assertSame(['shopware-tool-search'], $tools);
    }

    #[DataProvider('endpointAndEraProvider')]
    #[TestDox('The $_dataName advertises the toolsets of ?toolsets= from the first tools/list')]
    public function testToolsetsInTheUrlAreAdvertisedFromTheFirstToolsList(string $endpoint, McpEra $era): void
    {
        [$toolset, $tool] = self::toolsetOf($endpoint);

        $tools = $this->client($endpoint, $era, '?toolsets=' . $toolset)->toolNames();

        static::assertContains($tool, $tools);
        static::assertContains('shopware-tool-search', $tools);
    }

    #[DataProvider('endpointProvider')]
    #[TestDox('On the handshake era the $_dataName advertises an enabled toolset on the next tools/list of the session, together with list_changed')]
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
     * @return iterable<string, array{string, string, array<string, string>}>
     */
    public static function toolsetSessionToolProvider(): iterable
    {
        yield 'shopware-toolset-enable on the Admin API endpoint' => [self::ADMIN, 'shopware-toolset-enable', ['toolset' => 'entity']];
        yield 'shopware-toolsets-list on the Admin API endpoint' => [self::ADMIN, 'shopware-toolsets-list', []];
        yield 'shopware-toolset-enable on the Store API endpoint' => [self::STORE, 'shopware-toolset-enable', ['toolset' => 'store-api']];
        yield 'shopware-toolsets-list on the Store API endpoint' => [self::STORE, 'shopware-toolsets-list', []];
    }

    /**
     * @param array<string, string> $arguments
     */
    #[DataProvider('toolsetSessionToolProvider')]
    #[TestDox('On the stateless era $_dataName answers with an error that points to ?toolsets= and tool search, because there is no session to keep a toolset in')]
    public function testToolsetSessionToolAnswersAnErrorOnTheStatelessEra(string $endpoint, string $name, array $arguments): void
    {
        [, $tool] = self::toolsetOf($endpoint);
        $client = $this->client($endpoint, McpEra::Stateless);

        $result = $client->callTool($name, $arguments);

        static::assertTrue($result['isError'] ?? false, 'a toolset session tool must answer with an error without a session: ' . json_encode($result));
        static::assertStringContainsString('?toolsets=', McpEraClient::text($result));
        static::assertStringContainsString('shopware-tool-search', McpEraClient::text($result));
        static::assertNotContains($tool, $client->toolNames(), 'the call must not change the advertised tools');
    }

    #[DataProvider('endpointProvider')]
    #[TestDox('The server instructions of the $_dataName name the toolset session tools on the handshake era')]
    public function testHandshakeServerInstructionsNameTheToolsetSessionTools(string $endpoint): void
    {
        $instructions = $this->client($endpoint, McpEra::Handshake)->initializeResult()['instructions'] ?? null;

        static::assertIsString($instructions);
        static::assertStringContainsString('shopware-tool-search', $instructions);
        static::assertStringContainsString('shopware-toolset-enable', $instructions);
    }

    #[DataProvider('endpointProvider')]
    #[TestDox('The server instructions of the $_dataName point to ?toolsets= and tool search on the stateless era, not to the toolset session tools it does not offer')]
    public function testStatelessServerInstructionsPointToToolsetsInTheUrl(string $endpoint): void
    {
        $instructions = $this->client($endpoint, McpEra::Stateless)->result('server/discover')['instructions'] ?? null;

        static::assertIsString($instructions);
        static::assertStringContainsString('shopware-tool-search', $instructions);
        static::assertStringContainsString('?toolsets=', $instructions);
        static::assertStringNotContainsString('shopware-toolset-enable', $instructions);
        static::assertStringNotContainsString('shopware-toolsets-list', $instructions);
    }

    #[TestDox('The shopware-context prompt of the Admin API endpoint names shopware-toolset-enable on the handshake era')]
    public function testHandshakeContextPromptNamesToolsetEnable(): void
    {
        $text = $this->contextPrompt(McpEra::Handshake);

        static::assertStringContainsString('shopware-tool-search', $text);
        static::assertStringContainsString('shopware-toolset-enable', $text);
    }

    #[TestDox('The shopware-context prompt of the Admin API endpoint points to ?toolsets= on the stateless era, not to the toolset session tools it does not offer')]
    public function testStatelessContextPromptPointsToToolsetsInTheUrl(): void
    {
        $text = $this->contextPrompt(McpEra::Stateless);

        static::assertStringContainsString('shopware-tool-search', $text);
        static::assertStringContainsString('?toolsets=', $text);
        static::assertStringNotContainsString('shopware-toolset-enable', $text);
        static::assertStringNotContainsString('shopware-toolsets-list', $text);
    }

    #[DataProvider('endpointProvider')]
    #[TestDox('On the stateless era tool search of the $_dataName names the toolset of every match for ?toolsets=')]
    public function testToolSearchPointsToToolsetsInTheUrlOnTheStatelessEra(string $endpoint): void
    {
        [$toolset, $tool] = self::toolsetOf($endpoint);

        $structured = $this->client($endpoint, McpEra::Stateless)->callTool('shopware-tool-search', ['query' => $tool, 'maxResults' => 20])['structuredContent'] ?? null;

        static::assertIsArray($structured);
        static::assertIsString($structured['_meta']['usage'] ?? null);
        static::assertSame($toolset, self::toolsetsByTool($structured)[$tool] ?? null, (string) json_encode($structured));
        static::assertStringContainsString('?toolsets=', $structured['_meta']['usage']);
        static::assertStringNotContainsString('shopware-toolset-enable', $structured['_meta']['usage']);
    }

    #[DataProvider('endpointProvider')]
    #[TestDox('On the stateless era tool search of the $_dataName does not find the toolset session tools it does not offer')]
    public function testToolSearchDoesNotFindToolsetSessionToolsOnTheStatelessEra(string $endpoint): void
    {
        $structured = $this->client($endpoint, McpEra::Stateless)->callTool('shopware-tool-search', ['query' => 'toolset enable list', 'maxResults' => 20])['structuredContent'] ?? null;

        static::assertIsArray($structured);
        $found = array_keys(self::toolsetsByTool($structured));
        static::assertNotContains('shopware-toolset-enable', $found);
        static::assertNotContains('shopware-toolsets-list', $found);
    }

    #[DataProvider('eraProvider')]
    #[TestDox('An Admin API result above 100 KB on the $_dataName is linked with a signed pointer that any later request of the same principal can read')]
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

            static::assertIsList($stored, $readingEra->name . ' reads the bare result data, which both eras negotiate: ' . json_encode($read));
            static::assertNotSame([], $stored);
        }
    }

    #[DataProvider('eraProvider')]
    #[TestDox('A Store API result above 100 KB on the $_dataName is readable by any later request with the same sales channel and context token')]
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

    #[DataProvider('eraProvider')]
    #[TestDox('A Store API client without sw-context-token gets the result inline on the $_dataName, because no later request could present its one-off token')]
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
        static::assertGreaterThan(100_000, \strlen((string) json_encode($result)), 'the whole result must be inline');
    }

    /**
     * The handshake era uses sessions, which a client ends with `DELETE`.
     */
    #[DataProvider('endpointProvider')]
    #[TestDox('DELETE ends a handshake session on the $_dataName')]
    public function testDeleteEndsAHandshakeSession(string $endpoint): void
    {
        $client = $this->client($endpoint, McpEra::Handshake);
        $client->result('tools/list');

        $response = $client->delete();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), 'the specification names no success status, the MCP SDK answers 200: ' . $response->getContent());
        static::assertSame(Response::HTTP_NOT_FOUND, $client->post('tools/list')->getStatusCode(), 'the session must be gone after DELETE');
    }

    /**
     * The stateless era has no sessions, so a server that speaks only that era answers `DELETE` with 405.
     * A server that speaks both, like this one, handles `DELETE` like a handshake server:
     * it answers 400 when no session id is sent.
     */
    #[DataProvider('endpointProvider')]
    #[TestDox('DELETE without a session id is a bad request on the $_dataName, which also speaks the handshake era')]
    public function testDeleteWithoutASessionIdIsABadRequest(string $endpoint): void
    {
        $response = $this->client($endpoint, McpEra::Stateless)->delete();

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

    /**
     * @param array<string, mixed> $structured
     *
     * @return array<string, mixed> the toolset of each tool search match, by tool name
     */
    private static function toolsetsByTool(array $structured): array
    {
        $matches = $structured['result'] ?? [];
        static::assertIsArray($matches);

        return array_column(array_map(
            static fn (array $match): array => ['name' => $match['tool']['name'], 'toolset' => $match['toolset'] ?? null],
            $matches,
        ), 'toolset', 'name');
    }

    private function contextPrompt(McpEra $era): string
    {
        $messages = $this->client(self::ADMIN, $era)->result('prompts/get', ['name' => 'shopware-context'])['messages'] ?? null;
        static::assertIsArray($messages);

        return (string) json_encode($messages);
    }
}
