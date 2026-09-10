<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Framework\Indexing\CleanupIndicesTaskHandler;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CleanupIndicesTaskHandler::class)]
class CleanupIndicesTaskHandlerTest extends TestCase
{
    private const NOW = '2026-08-12 12:00:00';

    /**
     * @var array<string>
     */
    private array $deleted = [];

    /**
     * @var array<string>
     */
    private array $adminDeleted = [];

    protected function setUp(): void
    {
        $this->deleted = [];
        $this->adminDeleted = [];
    }

    public function testDeletesOutdatedStorefrontIndicesButNotInFlightOnes(): void
    {
        $handler = $this->createHandler(
            outdated: ['sw_product_1000', 'sw_product_2000'],
            inFlight: [['sw_product_2000']],
        );

        $handler->run();

        static::assertSame(['sw_product_1000'], $this->deleted);
    }

    public function testDeletesOutdatedAdminIndicesButNotInFlightOnes(): void
    {
        $handler = $this->createHandler(
            adminOutdated: ['sw-admin-product_1000', 'sw-admin-product_2000'],
            inFlight: [[], ['sw-admin-product_2000']],
            adminEnabled: true,
        );

        $handler->run();

        static::assertSame(['sw-admin-product_1000'], $this->adminDeleted);
    }

    public function testDoesNotTouchStorefrontIndicesWhenElasticsearchIsDisabled(): void
    {
        $handler = $this->createHandler(
            outdated: ['sw_product_1000'],
            adminOutdated: ['sw-admin-product_1000'],
            inFlight: [[]],
            elasticsearchEnabled: false,
            adminEnabled: true,
        );

        $handler->run();

        static::assertSame([], $this->deleted);
        static::assertSame(['sw-admin-product_1000'], $this->adminDeleted);
    }

    public function testDoesNotTouchAdminIndicesWhenAdminElasticsearchIsDisabled(): void
    {
        $handler = $this->createHandler(
            outdated: ['sw_product_1000'],
            adminOutdated: ['sw-admin-product_1000'],
            inFlight: [[]],
            adminEnabled: false,
        );

        $handler->run();

        static::assertSame(['sw_product_1000'], $this->deleted);
        static::assertSame([], $this->adminDeleted);
    }

    public function testSubtractsTheConfiguredMinimumAgeFromTheCurrentTime(): void
    {
        $captured = null;

        $detector = $this->createMock(ElasticsearchOutdatedIndexDetector::class);
        $detector
            ->method('getOutdated')
            ->willReturnCallback(static function (\DateTimeInterface $createdBefore) use (&$captured): array {
                $captured = $createdBefore;

                return [];
            });

        $handler = $this->createHandler(detector: $detector, minimumAge: 7200);

        $handler->run();

        static::assertInstanceOf(\DateTimeInterface::class, $captured);
        static::assertSame(
            (new \DateTimeImmutable(self::NOW))->getTimestamp() - 7200,
            $captured->getTimestamp()
        );
    }

    /**
     * @param array<string> $outdated
     * @param array<string> $adminOutdated
     * @param array<array<string>> $inFlight consecutive results of the indexing task lookups
     */
    private function createHandler(
        array $outdated = [],
        array $adminOutdated = [],
        array $inFlight = [[]],
        bool $elasticsearchEnabled = true,
        bool $adminEnabled = false,
        int $minimumAge = 86400,
        ?ElasticsearchOutdatedIndexDetector $detector = null,
    ): CleanupIndicesTaskHandler {
        if ($detector === null) {
            $detector = static::createStub(ElasticsearchOutdatedIndexDetector::class);
            $detector->method('getOutdated')->willReturn($outdated);
        }

        $adminDetector = static::createStub(AdminElasticsearchOutdatedIndexDetector::class);
        $adminDetector->method('getOutdated')->willReturn($adminOutdated);

        $adminEsHelper = static::createStub(AdminElasticsearchHelper::class);
        $adminEsHelper->method('isEnabled')->willReturn($adminEnabled);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturnOnConsecutiveCalls(...array_values($inFlight));

        return new CleanupIndicesTaskHandler(
            StaticEntityRepository::of(ScheduledTaskCollection::class, []),
            new NullLogger(),
            $this->createClient($this->deleted),
            $this->createClient($this->adminDeleted),
            $connection,
            $detector,
            $adminDetector,
            $adminEsHelper,
            new MockClock(self::NOW),
            $elasticsearchEnabled,
            $minimumAge,
        );
    }

    /**
     * @param array<string> $deleted
     */
    private function createClient(array &$deleted): Client
    {
        $indices = static::createStub(IndicesNamespace::class);
        $indices->method('delete')->willReturnCallback(static function (array $arguments) use (&$deleted): array {
            $deleted[] = $arguments['index'];

            return [];
        });

        $client = static::createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        return $client;
    }
}
