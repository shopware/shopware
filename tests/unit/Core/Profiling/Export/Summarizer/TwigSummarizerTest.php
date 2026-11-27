<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Export\Summarizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Export\Summarizer\TwigSummarizer;
use Symfony\Bridge\Twig\DataCollector\TwigDataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Twig\Profiler\Profile;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TwigSummarizer::class)]
class TwigSummarizerTest extends TestCase
{
    public function testSupportsOnlyTheTwigCollector(): void
    {
        static::assertTrue((new TwigSummarizer())->supports(new TwigDataCollector(new Profile())));
        static::assertFalse((new TwigSummarizer())->supports(new TimeDataCollector()));
    }

    public function testCountsTemplatesBlocksAndTheMostRenderedOnes(): void
    {
        $root = new Profile();
        $page = new Profile('page.html.twig', Profile::TEMPLATE, 'page.html.twig');
        $page->addProfile(new Profile('page.html.twig', Profile::BLOCK, 'content'));
        for ($i = 0; $i < 3; ++$i) {
            $page->addProfile(new Profile('card.html.twig', Profile::TEMPLATE, 'card.html.twig'));
        }
        $page->addProfile(new Profile('macros.html.twig', Profile::MACRO, 'price'));
        $root->addProfile($page);

        $collector = new TwigDataCollector($root);
        $collector->lateCollect();

        $summary = (new TwigSummarizer())->summarize($collector, null);

        static::assertSame(4, $summary['templates']);
        static::assertSame(1, $summary['blocks']);
        static::assertSame(1, $summary['macros']);
        static::assertSame(['card.html.twig' => 3, 'page.html.twig' => 1], $summary['mostRendered']);
        static::assertIsFloat($summary['timeMs']);
    }
}
