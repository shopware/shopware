<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Page\Robots\Parser\ParsedRobots;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsUserAgentBlock;

/**
 * @internal
 */
#[CoversClass(ParsedRobots::class)]
class ParsedRobotsTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $userAgentBlocks = [
            new RobotsUserAgentBlock('Googlebot', [
                new RobotsDirective('Crawl-delay', '10'),
            ]),
        ];

        $orphanedDirectives = [
            new RobotsDirective('Disallow', '/admin/'),
        ];

        $parsed = new ParsedRobots($userAgentBlocks, $orphanedDirectives);

        static::assertSame($userAgentBlocks, $parsed->getUserAgentBlocks());
        static::assertSame($orphanedDirectives, $parsed->getOrphanedPathDirectives());
        static::assertTrue($parsed->hasUserAgentBlocks());
    }

    public function testHasUserAgentBlocksReturnsFalseWhenNoBlocks(): void
    {
        $parsed = new ParsedRobots([], []);

        static::assertFalse($parsed->hasUserAgentBlocks());
    }

    public function testHasUserAgentBlocksReturnsTrueWhenBlocksPresent(): void
    {
        $userAgentBlocks = [
            new RobotsUserAgentBlock('Googlebot', [
                new RobotsDirective('Crawl-delay', '10'),
            ]),
        ];

        $parsed = new ParsedRobots($userAgentBlocks, []);

        static::assertTrue($parsed->hasUserAgentBlocks());
    }

    public function testWithEmptyArrays(): void
    {
        $parsed = new ParsedRobots([], []);

        static::assertCount(0, $parsed->getUserAgentBlocks());
        static::assertCount(0, $parsed->getOrphanedPathDirectives());
        static::assertFalse($parsed->hasUserAgentBlocks());
    }

    public function testImmutability(): void
    {
        $userAgentBlocks = [
            new RobotsUserAgentBlock('Googlebot', [
                new RobotsDirective('Crawl-delay', '10'),
            ]),
        ];

        $orphanedDirectives = [
            new RobotsDirective('Disallow', '/admin/'),
        ];

        $parsed = new ParsedRobots($userAgentBlocks, $orphanedDirectives);

        // Verify the arrays are the same objects (not cloned)
        static::assertSame($userAgentBlocks, $parsed->getUserAgentBlocks());
        static::assertSame($orphanedDirectives, $parsed->getOrphanedPathDirectives());
    }

    public function testGetUserAgentBlocksReturnsEmptyArrayWhenNoBlocks(): void
    {
        $parsed = new ParsedRobots([], []);

        $blocks = $parsed->getUserAgentBlocks();

        static::assertIsArray($blocks);
        static::assertEmpty($blocks);
    }

    public function testGetOrphanedPathDirectivesReturnsEmptyArrayWhenNoDirectives(): void
    {
        $parsed = new ParsedRobots([], []);

        $directives = $parsed->getOrphanedPathDirectives();

        static::assertIsArray($directives);
        static::assertEmpty($directives);
    }

    public function testMultipleUserAgentBlocks(): void
    {
        $userAgentBlocks = [
            new RobotsUserAgentBlock('Googlebot', [
                new RobotsDirective('Crawl-delay', '10'),
            ]),
            new RobotsUserAgentBlock('Bingbot', [
                new RobotsDirective('Disallow', '/admin/'),
            ]),
        ];

        $parsed = new ParsedRobots($userAgentBlocks, []);

        static::assertCount(2, $parsed->getUserAgentBlocks());
        static::assertTrue($parsed->hasUserAgentBlocks());

        $blocks = $parsed->getUserAgentBlocks();
        static::assertSame('Googlebot', $blocks[0]->userAgent);
        static::assertSame('Bingbot', $blocks[1]->userAgent);
    }

    public function testMultipleOrphanedDirectives(): void
    {
        $orphanedDirectives = [
            new RobotsDirective('Disallow', '/admin/'),
            new RobotsDirective('Allow', '/public/'),
        ];

        $parsed = new ParsedRobots([], $orphanedDirectives);

        static::assertCount(2, $parsed->getOrphanedPathDirectives());
        static::assertFalse($parsed->hasUserAgentBlocks());

        $directives = $parsed->getOrphanedPathDirectives();
        static::assertSame('Disallow', $directives[0]->type);
        static::assertSame('Allow', $directives[1]->type);
    }

    public function testMixedUserAgentBlocksAndOrphanedDirectives(): void
    {
        $userAgentBlocks = [
            new RobotsUserAgentBlock('Googlebot', [
                new RobotsDirective('Crawl-delay', '10'),
            ]),
        ];

        $orphanedDirectives = [
            new RobotsDirective('Disallow', '/admin/'),
        ];

        $parsed = new ParsedRobots($userAgentBlocks, $orphanedDirectives);

        static::assertCount(1, $parsed->getUserAgentBlocks());
        static::assertCount(1, $parsed->getOrphanedPathDirectives());
        static::assertTrue($parsed->hasUserAgentBlocks());
    }
}
