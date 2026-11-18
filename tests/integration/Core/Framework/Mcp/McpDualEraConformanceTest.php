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
 * Production pins both servers to the handshake era (`McpServerBuilderCompilerPass::pinHandshakeEra()`),
 * so this test boots a kernel of its own, builds a server with both eras from the same builder and swaps it
 * into both controllers of that kernel. The shared test kernel stays untouched.
 * Once production serves both eras, the fixture can go and the same scenarios run against the real container.
 *
 * A scenario the stateless era does not satisfy yet calls {@see self::knownStatelessGap()} right before
 * the ADR assertion that would fail. Everything before that call is asserted, so a regression on the
 * already working part still fails. Remove the call together with the fix.
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
    #[TestDox('Without ?toolsets= the $_dataName advertises only discovery tools on the stateless era, where the ADR leaves shopware-toolset-enable open')]
    public function testStatelessToolsListWithoutToolsetsAdvertisesOnlyDiscoveryTools(string $endpoint): void
    {
        $tools = $this->client($endpoint, McpEra::Stateless)->toolNames();

        static::assertContains('shopware-tool-search', $tools);
        static::assertContains('shopware-toolsets-list', $tools);
        static::assertSame([], array_values(array_diff($tools, self::DISCOVERY_TOOLS)), 'no domain tool may leak into the default surface');
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

    #[DataProvider('endpointProvider')]
    #[TestDox('On the stateless era the $_dataName answers shopware-toolset-enable with an error that points to ?toolsets=, because there is no session to keep the toolset in')]
    public function testToolsetEnableNeverSilentlyDoesNothingOnTheStatelessEra(string $endpoint): void
    {
        [$toolset, $tool] = self::toolsetOf($endpoint);
        $client = $this->client($endpoint, McpEra::Stateless);

        $result = $client->callTool('shopware-toolset-enable', ['toolset' => $toolset]);

        static::assertTrue($result['isError'] ?? false, 'enabling a toolset without a session must be an error: ' . json_encode($result));
        static::assertNotContains($tool, $client->toolNames());

        $this->knownStatelessGap('the error names the missing session, not "?toolsets="');
        static::assertStringContainsString('?toolsets=', McpEraClient::text($result));
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
     * Marks a stateless-era scenario whose ADR behaviour is not implemented yet.
     */
    private function knownStatelessGap(string $gap): void
    {
        static::markTestIncomplete('Stateless era not conformant yet: ' . $gap);
    }
}
