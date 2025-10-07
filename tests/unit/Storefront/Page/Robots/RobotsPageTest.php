<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Page\Robots\RobotsPage;
use Shopware\Storefront\Page\Robots\Struct\DomainRuleCollection;
use Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct;

/**
 * @internal
 */
#[CoversClass(RobotsPage::class)]
class RobotsPageTest extends TestCase
{
    public function testGetMergedUserAgentBlocksMergesAcrossDomains(): void
    {
        $page = new RobotsPage();

        $collection = new DomainRuleCollection();
        $collection->add(new DomainRuleStruct("User-agent: Googlebot\nDisallow: /private/", ''));
        $collection->add(new DomainRuleStruct("User-agent: Googlebot\nDisallow: /secret/", '/de'));

        $page->setDomainRules($collection);

        $merged = $page->getMergedUserAgentBlocks();

        static::assertCount(1, $merged, 'Should merge Googlebot blocks from both domains');
        static::assertSame('Googlebot', $merged[0]['userAgent']);
        static::assertCount(2, $merged[0]['rules'], 'Should have rules from both domains');
        static::assertSame('Disallow', $merged[0]['rules'][0]['type']);
        static::assertSame('/private/', $merged[0]['rules'][0]['path']);
        static::assertSame('Disallow', $merged[0]['rules'][1]['type']);
        static::assertSame('/de/secret/', $merged[0]['rules'][1]['path']);
    }

    public function testGetMergedUserAgentBlocksDeduplicatesNonPathRules(): void
    {
        $page = new RobotsPage();

        $collection = new DomainRuleCollection();
        $collection->add(new DomainRuleStruct("User-agent: Googlebot\nDisallow:", ''));
        $collection->add(new DomainRuleStruct("User-agent: Googlebot\nDisallow:", '/de'));

        $page->setDomainRules($collection);

        $merged = $page->getMergedUserAgentBlocks();

        static::assertCount(1, $merged, 'Should have one Googlebot block');
        static::assertCount(1, $merged[0]['rules'], 'Should deduplicate empty Disallow');
        static::assertSame('Disallow', $merged[0]['rules'][0]['type']);
        static::assertSame('', $merged[0]['rules'][0]['path']);
    }

    public function testGetMergedUserAgentBlocksDeduplicatesCrawlDelay(): void
    {
        $page = new RobotsPage();

        $collection = new DomainRuleCollection();
        $collection->add(new DomainRuleStruct("User-agent: Bingbot\nCrawl-delay: 10\nAllow: /", ''));
        $collection->add(new DomainRuleStruct("User-agent: Bingbot\nCrawl-delay: 10\nAllow: /public/", '/de'));

        $page->setDomainRules($collection);

        $merged = $page->getMergedUserAgentBlocks();

        static::assertCount(1, $merged, 'Should have one Bingbot block');
        static::assertSame('Bingbot', $merged[0]['userAgent']);

        // Count Crawl-delay occurrences
        $crawlDelayCount = 0;
        foreach ($merged[0]['rules'] as $rule) {
            if ($rule['type'] === 'Crawl-delay') {
                ++$crawlDelayCount;
            }
        }

        static::assertSame(1, $crawlDelayCount, 'Should deduplicate Crawl-delay');
        static::assertCount(3, $merged[0]['rules'], 'Should have 1 Crawl-delay + 2 Allow rules');
    }

    public function testGetMergedUserAgentBlocksKeepsAllPathBasedRules(): void
    {
        $page = new RobotsPage();

        $collection = new DomainRuleCollection();
        $collection->add(new DomainRuleStruct("Disallow: /account/\nAllow: /public/", ''));
        $collection->add(new DomainRuleStruct("Disallow: /account/\nAllow: /public/", '/de'));
        $collection->add(new DomainRuleStruct("Disallow: /account/\nAllow: /public/", '/fr'));

        $page->setDomainRules($collection);

        $merged = $page->getMergedUserAgentBlocks();

        static::assertCount(1, $merged, 'Should have one block (default)');
        static::assertCount(6, $merged[0]['rules'], 'Should have all 6 path rules (3 domains × 2 rules)');

        // Verify all domain paths are present
        $paths = array_column($merged[0]['rules'], 'path');
        static::assertContains('/account/', $paths);
        static::assertContains('/public/', $paths);
        static::assertContains('/de/account/', $paths);
        static::assertContains('/de/public/', $paths);
        static::assertContains('/fr/account/', $paths);
        static::assertContains('/fr/public/', $paths);
    }

    public function testGetMergedUserAgentBlocksHandlesMultipleUserAgents(): void
    {
        $page = new RobotsPage();

        $collection = new DomainRuleCollection();
        $collection->add(new DomainRuleStruct("User-agent: Googlebot\nDisallow:\nUser-agent: Bingbot\nCrawl-delay: 10", ''));

        $page->setDomainRules($collection);

        $merged = $page->getMergedUserAgentBlocks();

        static::assertCount(2, $merged, 'Should have two user-agent blocks');
        static::assertSame('Googlebot', $merged[0]['userAgent']);
        static::assertSame('Bingbot', $merged[1]['userAgent']);
    }

    public function testGetMergedUserAgentBlocksHandlesComplexScenario(): void
    {
        $page = new RobotsPage();

        $collection = new DomainRuleCollection();
        // Domain 1: Default rules + Googlebot
        $collection->add(new DomainRuleStruct("Disallow: /admin/\nUser-agent: Googlebot\nDisallow:", ''));
        // Domain 2: Default rules + Googlebot + Bingbot
        $collection->add(new DomainRuleStruct("Disallow: /admin/\nUser-agent: Googlebot\nDisallow:\nUser-agent: Bingbot\nCrawl-delay: 10\nAllow: /", '/de'));

        $page->setDomainRules($collection);

        $merged = $page->getMergedUserAgentBlocks();

        static::assertCount(3, $merged, 'Should have 3 blocks: default, Googlebot, Bingbot');

        // Block 1: Default rules (no user-agent)
        static::assertNull($merged[0]['userAgent']);
        static::assertCount(2, $merged[0]['rules'], 'Default block should have 2 Disallow rules');

        // Block 2: Googlebot
        static::assertSame('Googlebot', $merged[1]['userAgent']);
        static::assertCount(1, $merged[1]['rules'], 'Googlebot should have 1 deduplicated Disallow');
        static::assertSame('', $merged[1]['rules'][0]['path'], 'Should be empty Disallow');

        // Block 3: Bingbot
        static::assertSame('Bingbot', $merged[2]['userAgent']);
        static::assertCount(2, $merged[2]['rules'], 'Bingbot should have Crawl-delay + Allow');
    }

    public function testGetMergedUserAgentBlocksWithEmptyCollection(): void
    {
        $page = new RobotsPage();
        $page->setDomainRules(new DomainRuleCollection());

        $merged = $page->getMergedUserAgentBlocks();

        static::assertSame([], $merged, 'Should return empty array for empty collection');
    }

    public function testGetMergedUserAgentBlocksPreservesRuleOrder(): void
    {
        $page = new RobotsPage();

        $collection = new DomainRuleCollection();
        $collection->add(new DomainRuleStruct("User-agent: Googlebot\nDisallow: /a/\nDisallow: /b/\nAllow: /c/", ''));

        $page->setDomainRules($collection);

        $merged = $page->getMergedUserAgentBlocks();

        static::assertSame('Disallow', $merged[0]['rules'][0]['type']);
        static::assertSame('/a/', $merged[0]['rules'][0]['path']);
        static::assertSame('Disallow', $merged[0]['rules'][1]['type']);
        static::assertSame('/b/', $merged[0]['rules'][1]['path']);
        static::assertSame('Allow', $merged[0]['rules'][2]['type']);
        static::assertSame('/c/', $merged[0]['rules'][2]['path']);
    }
}
