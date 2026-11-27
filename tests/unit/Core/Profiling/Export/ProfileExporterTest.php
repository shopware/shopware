<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Export;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Export\CollectorDataConverter;
use Shopware\Core\Profiling\Export\ExportLimits;
use Shopware\Core\Profiling\Export\ProfileExporter;
use Shopware\Core\Profiling\Export\SecretRedactor;
use Shopware\Core\Profiling\Export\Summarizer\PanelSummarizer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProfileExporter::class)]
class ProfileExporterTest extends TestCase
{
    private const CONTEXT_TOKEN = '0123456789abcdef0123456789abcdef';

    private string $directory;

    private Profiler $profiler;

    private ProfileExporter $exporter;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/shopware-profile-export-' . bin2hex(random_bytes(4));
        $this->profiler = new Profiler(new FileProfilerStorage('file:' . $this->directory));
        $this->exporter = new ProfileExporter($this->profiler, new CollectorDataConverter(), new SecretRedactor(), [new StatsSummarizer()]);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testListsStoredProfilesOfOneTypeNewestFirst(): void
    {
        $this->store('aaa111', 'GET', 'https://shop.test/', 200, 1795753800);
        $this->store('bbb222', 'POST', 'https://shop.test/store-api/product', 500, 1795753900);
        $this->store('ccc333', 'CLI', 'bin/console cache:clear', 0, 1795754000, 'command');

        static::assertSame(
            [
                ['token' => 'bbb222', 'type' => 'request', 'method' => 'POST', 'url' => 'https://shop.test/store-api/product', 'status' => 500, 'time' => '2026-11-27T04:31:40+00:00', 'ip' => '127.0.0.1', 'parent' => null],
                ['token' => 'aaa111', 'type' => 'request', 'method' => 'GET', 'url' => 'https://shop.test/', 'status' => 200, 'time' => '2026-11-27T04:30:00+00:00', 'ip' => '127.0.0.1', 'parent' => null],
            ],
            $this->exporter->list(),
        );
        static::assertSame(['ccc333'], array_column($this->exporter->list(type: 'command'), 'token'));
        static::assertSame(['bbb222'], array_column($this->exporter->list(url: 'store-api'), 'token'));
        static::assertSame(['aaa111'], array_column($this->exporter->list(status: '200'), 'token'));
        static::assertSame(['bbb222'], array_column($this->exporter->list(limit: 1), 'token'));
    }

    public function testLoadsProfilesByTokenOrAsLatestMatch(): void
    {
        $this->store('aaa111', 'GET', 'https://shop.test/', 200, 1795753800);
        $this->store('bbb222', 'GET', 'https://shop.test/widgets/checkout/info', 204, 1795753900);

        static::assertSame('aaa111', $this->exporter->load('aaa111')?->getToken());
        static::assertSame('bbb222', $this->exporter->load(ProfileExporter::LATEST)?->getToken());
        static::assertSame('aaa111', $this->exporter->load(ProfileExporter::LATEST, url: '!widgets')?->getToken());
        static::assertNull($this->exporter->load(ProfileExporter::LATEST, method: 'DELETE'));
        static::assertNull($this->exporter->load('unknown'));
    }

    public function testNamesPanelsWithShortAliases(): void
    {
        $profile = $this->profile(
            new CollectorStub('time', []),
            new CollectorStub('app.connection_collector', []),
            new CollectorStub('Shopware\\Core\\Profiling\\Subscriber\\CacheTagCollectorSubscriber', []),
            new CollectorStub('Vendor\\First\\CartDataCollector', []),
            new CollectorStub('Vendor\\Second\\CartDataCollector', []),
        );

        static::assertSame(
            [
                'time' => 'time',
                'app.connection_collector' => 'db',
                'Shopware\\Core\\Profiling\\Subscriber\\CacheTagCollectorSubscriber' => 'cache_tag',
                'Vendor\\First\\CartDataCollector' => 'cart',
                'Vendor\\Second\\CartDataCollector' => 'Vendor\\Second\\CartDataCollector',
            ],
            $this->exporter->panels($profile),
        );
    }

    public function testExportsSummariesAndGenericPanelsWithinLimits(): void
    {
        $profile = $this->profile(
            new CollectorStub('stats', ['queries' => 3]),
            new CollectorStub('Vendor\\Plugin\\SearchDataCollector', ['terms' => ['a', 'b', 'c', 'd', 'e', 'f', 'g'], 'note' => str_repeat('x', 150)]),
        );

        $export = $this->exporter->export($profile);

        static::assertSame('aaa111', $export['token']);
        static::assertSame('summary', $export['mode']);
        static::assertTrue($export['redacted']);
        static::assertSame(
            [
                'stats' => ['summarized' => 3],
                'search' => ['terms' => ['a', 'b', 'c', 'd', 'e', '[… 2 more]'], 'note' => str_repeat('x', 120) . '…[30 more chars]'],
            ],
            $export['panels'],
        );
    }

    public function testSelectsPanelsByPanelOrCollectorName(): void
    {
        $profile = $this->profile(
            new CollectorStub('stats', ['queries' => 3]),
            new CollectorStub('Vendor\\Plugin\\SearchDataCollector', []),
            new CollectorStub('time', []),
        );

        static::assertSame(['stats', 'search'], array_keys($this->exporter->export($profile, ['stats', 'Vendor\\Plugin\\SearchDataCollector'])['panels']));
        static::assertSame(['search'], array_keys($this->exporter->export($profile, ['search'])['panels']));
    }

    public function testFullModeExportsRawDataWithoutSummaries(): void
    {
        $profile = $this->profile(new CollectorStub('stats', ['queries' => 3, 'list' => range(1, 20)]));

        $export = $this->exporter->export($profile, full: true);

        static::assertSame('full', $export['mode']);
        static::assertSame(['queries' => 3, 'list' => range(1, 20)], $export['panels']['stats']);
        static::assertSame(
            ['queries' => 3, 'list' => [1, 2, '[… 18 more]']],
            $this->exporter->export($profile, full: true, limits: new ExportLimits(depth: 0, items: 2, string: 0))['panels']['stats'],
        );
    }

    public function testRedactsSecretsAndScrubsLearnedOnesFromOtherPanels(): void
    {
        $request = Request::create('/account', server: ['HTTP_SW_CONTEXT_TOKEN' => self::CONTEXT_TOKEN, 'HTTP_AUTHORIZATION' => 'Bearer abcdef']);
        $requestCollector = new RequestDataCollector();
        $requestCollector->collect($request, new Response());
        $requestCollector->lateCollect();

        $profile = $this->profile(
            $requestCollector,
            new CollectorStub('Vendor\\Plugin\\SqlDataCollector', ['params' => ['WHERE token = ' . self::CONTEXT_TOKEN], 'password' => 'x']),
        );
        $profile->setUrl('https://shop.test/account?access_token=abc');

        $redacted = $this->exporter->export($profile, ['request', 'sql'], full: true);
        $json = (string) json_encode($redacted);

        static::assertStringNotContainsString(self::CONTEXT_TOKEN, $json);
        static::assertStringNotContainsString('abcdef', $json);
        static::assertSame('https://shop.test/account?access_token=[redacted]', $redacted['url']);
        static::assertSame(['params' => ['WHERE token = [redacted]'], 'password' => '[redacted]'], $redacted['panels']['sql']);

        $kept = $this->exporter->export($profile, ['sql'], full: true, redact: false);

        static::assertFalse($kept['redacted']);
        static::assertSame(['params' => ['WHERE token = ' . self::CONTEXT_TOKEN], 'password' => 'x'], $kept['panels']['sql']);
    }

    public function testDescribesParentAndChildProfiles(): void
    {
        $profile = $this->profile();
        $child = new Profile('ccc333');
        $child->setMethod('GET');
        $child->setUrl('https://shop.test/_fragment');
        $child->setStatusCode(200);
        $profile->addChild($child);

        $export = $this->exporter->export($profile);

        static::assertNull($export['parent']);
        static::assertSame([['token' => 'ccc333', 'method' => 'GET', 'url' => 'https://shop.test/_fragment', 'status' => 200]], $export['children']);
        static::assertSame('aaa111', $this->exporter->export($child)['parent']);
    }

    private function profile(DataCollectorInterface ...$collectors): Profile
    {
        $profile = new Profile('aaa111');
        $profile->setMethod('GET');
        $profile->setUrl('https://shop.test/');
        $profile->setStatusCode(200);
        $profile->setTime(1795753800);
        $profile->setIp('127.0.0.1');
        $profile->setCollectors($collectors);

        return $profile;
    }

    private function store(string $token, string $method, string $url, int $status, int $time, string $type = 'request'): void
    {
        $profile = new Profile($token);
        $profile->setMethod($method);
        $profile->setUrl($url);
        $profile->setStatusCode($status);
        $profile->setTime($time);
        $profile->setIp('127.0.0.1');
        $profile->setVirtualType($type);

        $this->profiler->saveProfile($profile);
    }
}

/**
 * @internal
 */
class CollectorStub extends DataCollector
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly string $name,
        array $data,
    ) {
        $this->data = $data;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function getName(): string
    {
        return $this->name;
    }
}

/**
 * @internal
 */
class StatsSummarizer implements PanelSummarizer
{
    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector->getName() === 'stats';
    }

    public function summarize(DataCollectorInterface $collector, mixed $data): array
    {
        return ['summarized' => \is_array($data) ? $data['queries'] : null];
    }
}
