<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Export\Summarizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Export\Summarizer\CacheTagSummarizer;
use Shopware\Core\Profiling\Subscriber\CacheTagCollectorSubscriber;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheTagSummarizer::class)]
class CacheTagSummarizerTest extends TestCase
{
    protected function tearDown(): void
    {
        CacheTagCollectorSubscriber::$tags = [];
    }

    public function testSupportsOnlyTheCacheTagCollector(): void
    {
        static::assertTrue((new CacheTagSummarizer())->supports(new CacheTagCollectorSubscriber(new RequestStack())));
        static::assertFalse((new CacheTagSummarizer())->supports(new TimeDataCollector()));
    }

    public function testListsTagsPerUriWithoutCallers(): void
    {
        $manyTags = [];
        for ($i = 0; $i < 105; ++$i) {
            $manyTags['product-' . $i] = ['ProductRoute::load | Controller::detail' => 1];
        }

        CacheTagCollectorSubscriber::$tags = [
            '/' => [
                'system.config-' => ['SystemConfigService::get | Subscriber::onRequest' => 2],
                'translator.en' => ['Translator::trans | Twig::render' => 1],
            ],
            '/detail/1' => $manyTags,
        ];
        $collector = new CacheTagCollectorSubscriber(new RequestStack());
        $collector->lateCollect();

        $summary = (new CacheTagSummarizer())->summarize($collector, null);

        static::assertSame(['count' => 2, 'tags' => ['system.config-', 'translator.en']], $summary['/']);
        static::assertSame(105, $summary['/detail/1']['count']);
        static::assertCount(101, $summary['/detail/1']['tags']);
        static::assertSame('[… 5 more]', $summary['/detail/1']['tags'][100]);
    }
}
