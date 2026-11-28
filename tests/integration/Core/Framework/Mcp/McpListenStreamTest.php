<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Mcp\Capability\Registry;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\ServerCapabilities;
use Mcp\Server;
use Mcp\Server\Builder;
use Mcp\Server\Stateless\RequestMeta;
use Mcp\Server\Stateless\StatelessProtocol;
use Mcp\Server\Subscription\NotificationBusInterface;
use Mcp\Server\Subscription\PublishingEventDispatcher;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistFilter;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\Controller\StoreApiMcpServerController;
use Shopware\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Mcp\Notification\McpListVersions;
use Shopware\Core\Framework\Mcp\Notification\McpListVersionsNotificationBus;
use Shopware\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopware\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * List changes on the stateless era, end to end: the Admin API server gets the list version bus the way
 * `shopware.mcp.protocol_era.admin: auto` wires it, the Store API server gets none.
 *
 * Like {@see McpDualEraConformanceTest}, this boots a kernel of its own to swap servers with both protocol legs
 * into the controllers. It runs without a database transaction on purpose: the bump comes from another database
 * connection and must be committed to be seen. The list versions are restored afterwards.
 *
 * What this cannot cover is a real PHP-FPM worker behind a web server; see "List change notifications" in
 * `src/Core/Framework/Mcp/AGENTS.md` for the manual check.
 *
 * @internal
 */
#[Package('framework')]
class McpListenStreamTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * Shorter than the production lifetime, so the test does not hold a stream for 25 seconds.
     */
    private const LIFETIME = 4.0;

    private static ?Kernel $listenKernel = null;

    public static function tearDownAfterClass(): void
    {
        self::$listenKernel?->shutdown();
        self::$listenKernel = null;
    }

    protected function setUp(): void
    {
        if (self::$listenKernel !== null) {
            return;
        }

        $kernel = KernelLifecycleManager::createKernel();
        $kernel->boot();

        $container = $kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);
        self::installServers($container);

        self::$listenKernel = $kernel;
    }

    protected function tearDown(): void
    {
        if ($this->salesChannelIds === []) {
            return;
        }

        static::getContainer()->get('sales_channel.repository')->delete(
            array_map(static fn (string $id): array => ['id' => $id], $this->salesChannelIds),
            Context::createDefaultContext()
        );
        $this->salesChannelIds = [];
    }

    public function testOnlyTheServerWithTheBusAnnouncesListChangedOnDiscover(): void
    {
        $admin = self::capabilities((string) self::kernel()->handle($this->adminRequest('server/discover'))->getContent());
        static::assertTrue($admin['tools']['listChanged'] ?? null, (string) json_encode($admin));
        static::assertTrue($admin['prompts']['listChanged'] ?? null, (string) json_encode($admin));

        $browser = $this->createSalesChannelBrowser(self::$listenKernel);
        $browser->request('POST', '/store-api/_mcp', [], [], self::statelessServer('server/discover'), (string) json_encode(self::body('server/discover')));
        $store = self::capabilities($browser->getInternalResponse()->getContent());
        static::assertArrayNotHasKey('listChanged', $store['tools'] ?? [], (string) json_encode($store));
    }

    public function testABumpFromAnotherProcessReachesAnOpenListenStream(): void
    {
        $connection = $this->container()->get(Connection::class);
        $saved = $connection->fetchAllAssociative('SELECT * FROM `mcp_list_version`');

        $otherConnection = DriverManager::getConnection($connection->getParams());
        $bumpedAt = null;

        try {
            $response = self::kernel()->handle($this->adminRequest('subscriptions/listen', [
                'notifications' => ['toolsListChanged' => true, 'promptsListChanged' => true],
            ]));
            static::assertInstanceOf(StreamedResponse::class, $response, 'a listen stream must be streamed, not buffered');
            static::assertSame('no', $response->headers->get('X-Accel-Buffering'));

            $frames = self::sendAndTimeFrames($response, static function () use ($otherConnection, &$bumpedAt): void {
                $bumpedAt = self::bumpTools($otherConnection);
            });
        } finally {
            $otherConnection->close();
            $connection->executeStatement('DELETE FROM `mcp_list_version`');
            foreach ($saved as $row) {
                $connection->insert('mcp_list_version', $row);
            }
        }

        static::assertNotNull($bumpedAt, 'the stream sent no keep-alive, so the tools version was never bumped');

        $ack = self::firstFrameWith($frames, StatelessProtocol::ACKNOWLEDGED_NOTIFICATION);
        static::assertLessThan($frames[0][0] + 1.0, $ack[0], 'the acknowledgement must be flushed right away, not when the stream ends');

        $toolsChanged = self::firstFrameWith($frames, 'notifications/tools/list_changed');
        static::assertStringContainsString(RequestMeta::SUBSCRIPTION_ID, $toolsChanged[1]);
        static::assertGreaterThanOrEqual($bumpedAt, $toolsChanged[0]);
        static::assertLessThan(
            $bumpedAt + McpListVersionsNotificationBus::CACHE_TTL + 0.75,
            $toolsChanged[0],
            'the bump must reach the stream within the cache lifetime plus one poll',
        );
        static::assertNull(self::firstFrameWith($frames, 'notifications/prompts/list_changed', required: false), 'only the bumped list is announced');

        static::assertStringContainsString('"resultType":"complete"', end($frames)[1], 'the stream ends gracefully after its lifetime');
    }

    #[Depends('testOnlyTheServerWithTheBusAnnouncesListChangedOnDiscover')]
    public function testTheClassLeavesNoStorefrontSalesChannelBehind(): void
    {
        $leaked = $this->container()->get(Connection::class)->fetchFirstColumn(
            'SELECT LOWER(HEX(`sales_channel_id`)) FROM `sales_channel_domain` WHERE `url` = :url',
            ['url' => 'http://localhost']
        );

        static::assertSame([], $leaked, 'a sales channel with the domain http://localhost was committed and never removed');
    }

    private static function installServers(ContainerInterface $container): void
    {
        $listVersions = $container->get(McpListVersions::class);
        $cache = $container->get('cache.app');
        \assert($listVersions instanceof McpListVersions && $cache instanceof CacheInterface);

        $container->set(McpServerController::class, new McpServerController(
            self::server($container, 'mcp.server.admin.builder', new McpListVersionsNotificationBus($listVersions, $cache, new NullLogger())),
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
            self::server($container, 'mcp.server.store_api.builder', null),
            $container->get(McpHttpTransportFactory::class),
            $container->get(McpRateLimiter::class),
            $container->get(McpSessionIdValidator::class),
            $container->get(LoggerInterface::class),
            $storeNotifier,
        ));
    }

    /**
     * Wires the bus like McpServerBuilderCompilerPass does on `auto`: on the builder, and as the publishing
     * dispatcher of the registry, which the SDK requires before it announces `listChanged`. The capabilities
     * the container's builder states are those of `legacy`, so they are stated again with `listChanged`. A
     * clone, so the builder of the container stays as it is.
     */
    private static function server(ContainerInterface $container, string $builderId, ?NotificationBusInterface $bus): Server
    {
        $builder = $container->get($builderId);
        \assert($builder instanceof Builder);
        $builder = (clone $builder)->setModernVersions([ProtocolVersion::V2026_07_28]);

        if ($bus !== null) {
            $builder
                ->setRegistry(new Registry(new PublishingEventDispatcher($bus), new NullLogger()))
                ->setNotificationBus($bus)
                ->setSubscriptionLifetime(self::LIFETIME)
                ->setCapabilities(new ServerCapabilities(
                    toolsListChanged: true,
                    resourcesListChanged: true,
                    promptsListChanged: true,
                    logging: true,
                    completions: true,
                ));
        }

        return $builder->build();
    }

    /**
     * Bumps the tools version the way the CLI does it (`app:install`, `plugin:install`): through the same statement
     * as McpListVersions::bump(), committed on a connection the stream does not use, so the stream can only see it
     * through the table. Returns the time it committed.
     *
     * Not a separate PHP process: one spawned through PhpProcess hung before running its first line in some
     * random test orders, which made this test fail on a 60 s process timeout.
     */
    private static function bumpTools(Connection $connection): float
    {
        $connection->executeStatement(
            'INSERT INTO `mcp_list_version` (`list`, `version`, `updated_at`) VALUES (\'tools\', 1, NOW(3))
             ON DUPLICATE KEY UPDATE `version` = `version` + 1, `updated_at` = NOW(3)'
        );

        return microtime(true);
    }

    /**
     * Sends the stream the way PHP-FPM would and records when each frame leaves PHP's output buffer.
     *
     * $onPolling runs once, on the first keep-alive: only then has the stream read the versions it compares
     * against. The acknowledgement is flushed before that read, so a bump there would not count as a change.
     *
     * @return non-empty-list<array{float, string}>
     */
    private static function sendAndTimeFrames(StreamedResponse $response, callable $onPolling): array
    {
        $frames = [[microtime(true), '']];
        $polling = false;

        ob_start(static function (string $chunk) use (&$frames, &$polling, $onPolling): string {
            if ($chunk !== '') {
                $frames[] = [microtime(true), $chunk];
                if (!$polling && str_starts_with($chunk, ': keep-alive')) {
                    $polling = true;
                    $onPolling();
                }
            }

            return '';
        }, 1);

        try {
            $response->sendContent();
        } finally {
            ob_end_clean();
        }

        return $frames;
    }

    /**
     * @param list<array{float, string}> $frames
     *
     * @return ($required is true ? array{float, string} : array{float, string}|null)
     */
    private static function firstFrameWith(array $frames, string $method, bool $required = true): ?array
    {
        foreach ($frames as $frame) {
            if (str_contains($frame[1], '"method":"' . $method . '"')) {
                return $frame;
            }
        }

        if ($required) {
            static::fail(\sprintf('No frame with %s in: %s', $method, implode('', array_column($frames, 1))));
        }

        return null;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function adminRequest(string $method, array $params = []): Request
    {
        $browser = $this->createClient(self::$listenKernel);

        $server = self::statelessServer($method) + [
            'HTTP_AUTHORIZATION' => (string) $browser->getServerParameter('HTTP_Authorization'),
        ];
        $server[PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT] = $browser->getServerParameter(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        return Request::create('http://localhost/api/_mcp', 'POST', [], [], [], $server, (string) json_encode(self::body($method, $params)));
    }

    /**
     * @return array<string, mixed>
     */
    private static function capabilities(string $content): array
    {
        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body['result']['capabilities'] ?? null, $content);

        return $body['result']['capabilities'];
    }

    /**
     * @return array<string, string>
     */
    private static function statelessServer(string $method): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => ProtocolVersion::V2026_07_28->value,
            'HTTP_MCP_METHOD' => $method,
        ];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private static function body(string $method, array $params = []): array
    {
        $params['_meta'] = [
            RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value,
            RequestMeta::CLIENT_CAPABILITIES => new \stdClass(),
            RequestMeta::CLIENT_INFO => ['name' => 'mcp-listen-stream-test', 'version' => '1.0'],
        ];

        return ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params, 'id' => 1];
    }

    private static function kernel(): Kernel
    {
        \assert(self::$listenKernel !== null);

        return self::$listenKernel;
    }

    private function container(): ContainerInterface
    {
        $container = self::kernel()->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);

        return $container;
    }
}
