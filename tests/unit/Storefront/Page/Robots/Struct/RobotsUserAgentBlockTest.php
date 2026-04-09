<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirectiveType;
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
            new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10'),
            new RobotsDirective(RobotsDirectiveType::DISALLOW, '/admin/'),
            new RobotsDirective(RobotsDirectiveType::ALLOW, '/public/'),
            new RobotsDirective(RobotsDirectiveType::SITEMAP, 'https://example.com/sitemap.xml'),
        ];

        $block = new RobotsUserAgentBlock('Googlebot', $directives);
        $pathDirectives = $block->getPathDirectives();

        static::assertCount(2, $pathDirectives);
        static::assertContainsOnlyInstancesOf(RobotsDirective::class, $pathDirectives);

        $types = array_map(static fn (RobotsDirective $d) => $d->type, $pathDirectives);
        static::assertContains(RobotsDirectiveType::DISALLOW, $types);
        static::assertContains(RobotsDirectiveType::ALLOW, $types);
    }

    public function testGetNonPathDirectives(): void
    {
        $directives = [
            new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10'),
            new RobotsDirective(RobotsDirectiveType::DISALLOW, '/admin/'),
            new RobotsDirective(RobotsDirectiveType::ALLOW, '/public/'),
            new RobotsDirective(RobotsDirectiveType::SITEMAP, 'https://example.com/sitemap.xml'),
        ];

        $block = new RobotsUserAgentBlock('Googlebot', $directives);
        $nonPathDirectives = $block->getNonPathDirectives();

        static::assertCount(2, $nonPathDirectives);
        static::assertContainsOnlyInstancesOf(RobotsDirective::class, $nonPathDirectives);

        $types = array_map(static fn (RobotsDirective $d) => $d->type, $nonPathDirectives);
        static::assertContains(RobotsDirectiveType::CRAWL_DELAY, $types);
        static::assertContains(RobotsDirectiveType::SITEMAP, $types);
    }

    public function testRender(): void
    {
        $directives = [
            new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10'),
            new RobotsDirective(RobotsDirectiveType::DISALLOW, '/admin/'),
        ];

        $block = new RobotsUserAgentBlock('Googlebot', $directives);
        $output = $block->render();

        $expected = "User-agent: Googlebot\nCrawl-delay: 10\nDisallow: /admin/";
        static::assertSame($expected, $output);
    }

    public function testGetHashIsConsistentForSameBlock(): void
    {
        $directives = [
            new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10'),
            new RobotsDirective(RobotsDirectiveType::DISALLOW, '/admin/'),
        ];

        $block1 = new RobotsUserAgentBlock('Googlebot', $directives);
        $block2 = new RobotsUserAgentBlock('Googlebot', $directives);

        static::assertSame($block1->getHash(), $block2->getHash());
    }

    public function testGetHashDiffersForDifferentUserAgents(): void
    {
        $directives = [
            new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10'),
        ];

        $block1 = new RobotsUserAgentBlock('Googlebot', $directives);
        $block2 = new RobotsUserAgentBlock('Bingbot', $directives);

        static::assertNotSame($block1->getHash(), $block2->getHash());
    }

    public function testGetHashDiffersForDifferentNonPathDirectives(): void
    {
        $block1 = new RobotsUserAgentBlock('Googlebot', [
            new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10'),
        ]);

        $block2 = new RobotsUserAgentBlock('Googlebot', [
            new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '20'),
        ]);

        static::assertNotSame($block1->getHash(), $block2->getHash());
    }

    public function testGetHashIgnoresPathDirectives(): void
    {
        $block1 = new RobotsUserAgentBlock('Googlebot', [
            new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10'),
            new RobotsDirective(RobotsDirectiveType::DISALLOW, '/admin/'),
        ]);

        $block2 = new RobotsUserAgentBlock('Googlebot', [
            new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10'),
            new RobotsDirective(RobotsDirectiveType::DISALLOW, '/different/'),
        ]);

        // Hash should be the same because path directives are ignored
        static::assertSame($block1->getHash(), $block2->getHash());
    }
}
