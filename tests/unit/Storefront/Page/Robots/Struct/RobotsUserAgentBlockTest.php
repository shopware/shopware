<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsUserAgentBlock;

/**
 * @internal
 */
#[CoversClass(RobotsUserAgentBlock::class)]
class RobotsUserAgentBlockTest extends TestCase
{
    public function testGetPathDirectives(): void
    {
        $directives = [
            new RobotsDirective('Crawl-delay', '10'),
            new RobotsDirective('Disallow', '/admin/'),
            new RobotsDirective('Allow', '/public/'),
            new RobotsDirective('Sitemap', 'https://example.com/sitemap.xml'),
        ];

        $block = new RobotsUserAgentBlock('Googlebot', $directives);
        $pathDirectives = $block->getPathDirectives();

        static::assertCount(2, $pathDirectives);
        static::assertContainsOnlyInstancesOf(RobotsDirective::class, $pathDirectives);

        $types = array_map(static fn (RobotsDirective $d) => $d->type, $pathDirectives);
        static::assertContains('Disallow', $types);
        static::assertContains('Allow', $types);
    }

    public function testGetNonPathDirectives(): void
    {
        $directives = [
            new RobotsDirective('Crawl-delay', '10'),
            new RobotsDirective('Disallow', '/admin/'),
            new RobotsDirective('Allow', '/public/'),
            new RobotsDirective('Sitemap', 'https://example.com/sitemap.xml'),
        ];

        $block = new RobotsUserAgentBlock('Googlebot', $directives);
        $nonPathDirectives = $block->getNonPathDirectives();

        static::assertCount(2, $nonPathDirectives);
        static::assertContainsOnlyInstancesOf(RobotsDirective::class, $nonPathDirectives);

        $types = array_map(static fn (RobotsDirective $d) => $d->type, $nonPathDirectives);
        static::assertContains('Crawl-delay', $types);
        static::assertContains('Sitemap', $types);
    }

    public function testRender(): void
    {
        $directives = [
            new RobotsDirective('Crawl-delay', '10'),
            new RobotsDirective('Disallow', '/admin/'),
        ];

        $block = new RobotsUserAgentBlock('Googlebot', $directives);
        $output = $block->render();

        $expected = "User-agent: Googlebot\nCrawl-delay: 10\nDisallow: /admin/";
        static::assertSame($expected, $output);
    }

    public function testGetHashIsConsistentForSameBlock(): void
    {
        $directives = [
            new RobotsDirective('Crawl-delay', '10'),
            new RobotsDirective('Disallow', '/admin/'),
        ];

        $block1 = new RobotsUserAgentBlock('Googlebot', $directives);
        $block2 = new RobotsUserAgentBlock('Googlebot', $directives);

        static::assertSame($block1->getHash(), $block2->getHash());
    }

    public function testGetHashDiffersForDifferentUserAgents(): void
    {
        $directives = [
            new RobotsDirective('Crawl-delay', '10'),
        ];

        $block1 = new RobotsUserAgentBlock('Googlebot', $directives);
        $block2 = new RobotsUserAgentBlock('Bingbot', $directives);

        static::assertNotSame($block1->getHash(), $block2->getHash());
    }

    public function testGetHashDiffersForDifferentNonPathDirectives(): void
    {
        $block1 = new RobotsUserAgentBlock('Googlebot', [
            new RobotsDirective('Crawl-delay', '10'),
        ]);

        $block2 = new RobotsUserAgentBlock('Googlebot', [
            new RobotsDirective('Crawl-delay', '20'),
        ]);

        static::assertNotSame($block1->getHash(), $block2->getHash());
    }

    public function testGetHashIgnoresPathDirectives(): void
    {
        $block1 = new RobotsUserAgentBlock('Googlebot', [
            new RobotsDirective('Crawl-delay', '10'),
            new RobotsDirective('Disallow', '/admin/'),
        ]);

        $block2 = new RobotsUserAgentBlock('Googlebot', [
            new RobotsDirective('Crawl-delay', '10'),
            new RobotsDirective('Disallow', '/different/'),
        ]);

        // Hash should be the same because path directives are ignored
        static::assertSame($block1->getHash(), $block2->getHash());
    }
}
