<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Page\Robots\Parser\ParsedRobots;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirectiveType;
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
                new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10'),
            ]),
        ];

        $orphanedDirectives = [
            new RobotsDirective(RobotsDirectiveType::DISALLOW, '/admin/'),
        ];

        $parsed = new ParsedRobots($userAgentBlocks, $orphanedDirectives);

        static::assertSame($userAgentBlocks, $parsed->userAgentBlocks);
        static::assertSame($orphanedDirectives, $parsed->orphanedPathDirectives);
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
                new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10'),
            ]),
        ];

        $parsed = new ParsedRobots($userAgentBlocks, []);

        static::assertTrue($parsed->hasUserAgentBlocks());
    }

    public function testWithEmptyArrays(): void
    {
        $parsed = new ParsedRobots([], []);

        static::assertCount(0, $parsed->userAgentBlocks);
        static::assertCount(0, $parsed->orphanedPathDirectives);
        static::assertFalse($parsed->hasUserAgentBlocks());
    }

    public function testImmutability(): void
    {
        $userAgentBlocks = [
            new RobotsUserAgentBlock('Googlebot', [
                new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10'),
            ]),
        ];

        $orphanedDirectives = [
            new RobotsDirective(RobotsDirectiveType::DISALLOW, '/admin/'),
        ];

        $parsed = new ParsedRobots($userAgentBlocks, $orphanedDirectives);

        // Verify the arrays are the same objects (not cloned)
        static::assertSame($userAgentBlocks, $parsed->userAgentBlocks);
        static::assertSame($orphanedDirectives, $parsed->orphanedPathDirectives);
    }

    public function testGetUserAgentBlocksReturnsEmptyArrayWhenNoBlocks(): void
    {
        $parsed = new ParsedRobots([], []);

        $blocks = $parsed->userAgentBlocks;

        static::assertIsArray($blocks);
        static::assertEmpty($blocks);
    }

    public function testGetOrphanedPathDirectivesReturnsEmptyArrayWhenNoDirectives(): void
    {
        $parsed = new ParsedRobots([], []);

        $directives = $parsed->orphanedPathDirectives;

        static::assertIsArray($directives);
        static::assertEmpty($directives);
    }

    public function testMultipleUserAgentBlocks(): void
    {
        $userAgentBlocks = [
            new RobotsUserAgentBlock('Googlebot', [
                new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10'),
            ]),
            new RobotsUserAgentBlock('Bingbot', [
                new RobotsDirective(RobotsDirectiveType::DISALLOW, '/admin/'),
            ]),
        ];

        $parsed = new ParsedRobots($userAgentBlocks, []);

        static::assertCount(2, $parsed->userAgentBlocks);
        static::assertTrue($parsed->hasUserAgentBlocks());

        $blocks = $parsed->userAgentBlocks;
        static::assertSame('Googlebot', $blocks[0]->userAgent);
        static::assertSame('Bingbot', $blocks[1]->userAgent);
    }

    public function testMultipleOrphanedDirectives(): void
    {
        $orphanedDirectives = [
            new RobotsDirective(RobotsDirectiveType::DISALLOW, '/admin/'),
            new RobotsDirective(RobotsDirectiveType::ALLOW, '/public/'),
        ];

        $parsed = new ParsedRobots([], $orphanedDirectives);

        static::assertCount(2, $parsed->orphanedPathDirectives);
        static::assertFalse($parsed->hasUserAgentBlocks());

        $directives = $parsed->orphanedPathDirectives;
        static::assertSame(RobotsDirectiveType::DISALLOW, $directives[0]->type);
        static::assertSame(RobotsDirectiveType::ALLOW, $directives[1]->type);
    }

    public function testMixedUserAgentBlocksAndOrphanedDirectives(): void
    {
        $userAgentBlocks = [
            new RobotsUserAgentBlock('Googlebot', [
                new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10'),
            ]),
        ];

        $orphanedDirectives = [
            new RobotsDirective(RobotsDirectiveType::DISALLOW, '/admin/'),
        ];

        $parsed = new ParsedRobots($userAgentBlocks, $orphanedDirectives);

        static::assertCount(1, $parsed->userAgentBlocks);
        static::assertCount(1, $parsed->orphanedPathDirectives);
        static::assertTrue($parsed->hasUserAgentBlocks());
    }
}
