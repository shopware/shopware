<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\RuleLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\DataAbstractionLayer\CallSiteResolver;
use Shopware\Core\Profiling\DataAbstractionLayer\DalCollector;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityAggregator;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityReader;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntitySearcher;
use Shopware\Core\Profiling\DataAbstractionLayer\ProfilingEntityWriter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CallSiteResolver::class)]
class CallSiteResolverTest extends TestCase
{
    public function testTheCallerOfARepositoryIsTheFirstFrameOutsideTheDal(): void
    {
        $trace = (new CallSiteResolver())->resolve([
            ...$this->recording('traceRead'),
            $this->frame(ProfilingEntityReader::class, 'read', 'EntityRepository.php', 330),
            $this->frame(EntityRepository::class, 'read', 'EntityRepository.php', 290),
            $this->frame(EntityRepository::class, 'search', 'ProductListingLoader.php', 477),
            $this->frame(ProductListingLoader::class, 'resolveData', 'ProductListingLoader.php', 120),
        ]);

        static::assertSame(['call' => EntityRepository::class . '::search', 'file' => 'ProductListingLoader.php', 'line' => 477], $trace[0]);
        static::assertSame(ProductListingLoader::class . '::resolveData', $trace[1]['call']);
    }

    public function testADalHelperOutsideARepositoryIsTheEntryPoint(): void
    {
        $trace = (new CallSiteResolver())->resolve([
            ...$this->recording('traceRead'),
            $this->frame(ProfilingEntitySearcher::class, 'search', 'EntityRepository.php', 370),
            $this->frame(EntityRepository::class, 'search', 'RepositoryIterator.php', 60),
            $this->frame(RepositoryIterator::class, 'fetch', 'RuleLoader.php', 47),
            $this->frame(RuleLoader::class, 'load', 'CartRuleLoader.php', 80),
        ]);

        static::assertSame(RepositoryIterator::class . '::fetch', $trace[0]['call']);
        static::assertSame(RuleLoader::class . '::load', $trace[1]['call']);
    }

    public function testANestedCallNamesTheCodeThatCalledTheRepository(): void
    {
        $trace = (new CallSiteResolver())->resolve([
            ...$this->recording('traceRead'),
            $this->frame(ProfilingEntityReader::class, 'read', 'EntityRepository.php', 330),
            $this->frame(EntityRepository::class, 'search', 'EntityAggregator.php', 585),
            $this->frame(EntityAggregator::class, 'hydrateEntityAggregation', 'EntityAggregator.php', 140),
            $this->frame(EntityAggregator::class, 'aggregate', 'ElasticsearchEntityAggregator.php', 42),
            $this->frame(ElasticsearchEntityAggregator::class, 'aggregate', 'ProfilingEntityAggregator.php', 30),
            $this->frame(ProfilingEntityAggregator::class, '{closure}', 'DalCollector.php', 600),
            ...$this->recording('traceRead'),
            $this->frame(ProfilingEntityAggregator::class, 'aggregate', 'SalesChannelRepository.php', 150),
            $this->frame(SalesChannelRepository::class, 'aggregate', 'ProductListingLoader.php', 171),
            $this->frame(ProductListingLoader::class, 'load', 'ProductListingRoute.php', 90),
        ]);

        static::assertSame(['call' => EntityRepository::class . '::search', 'file' => 'EntityAggregator.php', 'line' => 585], $trace[0]);
        static::assertSame(EntityAggregator::class . '::hydrateEntityAggregation', $trace[1]['call']);
    }

    public function testADirectWriterCallIsNamedAfterTheInterface(): void
    {
        $trace = (new CallSiteResolver())->resolve([
            ...$this->recording('traceSync'),
            $this->frame(ProfilingEntityWriter::class, 'sync', 'SyncService.php', 72),
            $this->frame(SyncService::class, 'doSync', 'SyncService.php', 50),
        ]);

        static::assertSame(['call' => EntityWriterInterface::class . '::sync', 'file' => 'SyncService.php', 'line' => 72], $trace[0]);
        static::assertSame(SyncService::class . '::doSync', $trace[1]['call']);
    }

    /**
     * @return list<array{function: string, line: int, file: string, class: string}>
     */
    private function recording(string $method): array
    {
        return [
            $this->frame(DalCollector::class, 'record', 'DalCollector.php', 200),
            $this->frame(DalCollector::class, $method, 'ProfilingEntityReader.php', 25),
        ];
    }

    /**
     * @return array{function: string, line: int, file: string, class: string}
     */
    private function frame(string $class, string $function, string $file, int $line): array
    {
        return ['function' => $function, 'line' => $line, 'file' => $file, 'class' => $class];
    }
}
