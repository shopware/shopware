<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Page\Robots\Parser\RobotsDirectiveParser;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirectiveType;

/**
 * @internal
 */
#[CoversClass(RobotsDirectiveParser::class)]
class RobotsDirectiveParserTest extends TestCase
{
    private RobotsDirectiveParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RobotsDirectiveParser();
    }

    public function testParseEmptyString(): void
    {
        $result = $this->parser->parse('');

        static::assertCount(0, $result->getUserAgentBlocks());
        static::assertCount(0, $result->getOrphanedPathDirectives());
        static::assertFalse($result->hasUserAgentBlocks());
    }

    public function testParseUserAgentBlockWithDirectives(): void
    {
        $text = <<<'TXT'
User-agent: Googlebot
Crawl-delay: 10
Disallow: /admin/
TXT;

        $result = $this->parser->parse($text);

        static::assertTrue($result->hasUserAgentBlocks());
        static::assertCount(1, $result->getUserAgentBlocks());

        $block = $result->getUserAgentBlocks()[0];
        static::assertSame('Googlebot', $block->userAgent);
        static::assertCount(2, $block->directives);

        $types = array_map(static fn (RobotsDirective $d) => $d->type, $block->directives);
        static::assertContains(RobotsDirectiveType::CRAWL_DELAY, $types);
        static::assertContains(RobotsDirectiveType::DISALLOW, $types);
    }

    public function testParseMultipleUserAgentBlocks(): void
    {
        $text = <<<'TXT'
User-agent: Googlebot
Crawl-delay: 10
Disallow: /admin/

User-agent: Bingbot
Disallow: /secret/
TXT;

        $result = $this->parser->parse($text);

        static::assertCount(2, $result->getUserAgentBlocks());

        $block1 = $result->getUserAgentBlocks()[0];
        static::assertSame('Googlebot', $block1->userAgent);
        static::assertCount(2, $block1->directives);

        $block2 = $result->getUserAgentBlocks()[1];
        static::assertSame('Bingbot', $block2->userAgent);
        static::assertCount(1, $block2->directives);
    }

    public function testParseMultipleUserAgentsForSameBlock(): void
    {
        $text = <<<'TXT'
User-agent: Googlebot
User-agent: Bingbot
Disallow: /admin/
TXT;

        $result = $this->parser->parse($text);

        static::assertCount(2, $result->getUserAgentBlocks());

        // Both user agents should have the same directive
        static::assertSame('Googlebot', $result->getUserAgentBlocks()[0]->userAgent);
        static::assertSame('Bingbot', $result->getUserAgentBlocks()[1]->userAgent);
        static::assertCount(1, $result->getUserAgentBlocks()[0]->directives);
        static::assertCount(1, $result->getUserAgentBlocks()[1]->directives);
    }

    public function testParseOrphanedPathDirectives(): void
    {
        $text = <<<'TXT'
Disallow: /admin/
Allow: /public/
TXT;

        $result = $this->parser->parse($text);

        static::assertFalse($result->hasUserAgentBlocks());
        static::assertCount(2, $result->getOrphanedPathDirectives());

        $types = array_map(static fn (RobotsDirective $d) => $d->type, $result->getOrphanedPathDirectives());
        static::assertContains(RobotsDirectiveType::DISALLOW, $types);
        static::assertContains(RobotsDirectiveType::ALLOW, $types);
    }

    public function testParseIgnoresComments(): void
    {
        $text = <<<'TXT'
# This is a comment
User-agent: Googlebot
# Another comment
Disallow: /admin/
TXT;

        $result = $this->parser->parse($text);

        static::assertCount(1, $result->getUserAgentBlocks());
        $block = $result->getUserAgentBlocks()[0];
        static::assertCount(1, $block->directives);
    }

    public function testParseIgnoresEmptyLines(): void
    {
        $text = <<<'TXT'

User-agent: Googlebot

Disallow: /admin/

TXT;

        $result = $this->parser->parse($text);

        static::assertCount(1, $result->getUserAgentBlocks());
        $block = $result->getUserAgentBlocks()[0];
        static::assertCount(1, $block->directives);
    }

    public function testParseIgnoresUnknownDirectives(): void
    {
        $text = <<<'TXT'
User-agent: Googlebot
Unknown-directive: value
Disallow: /admin/
TXT;

        $result = $this->parser->parse($text);

        static::assertCount(1, $result->getUserAgentBlocks());
        $block = $result->getUserAgentBlocks()[0];
        static::assertCount(1, $block->directives);
        static::assertSame(RobotsDirectiveType::DISALLOW, $block->directives[0]->type);
    }

    public function testParseIgnoresMalformedLines(): void
    {
        $text = <<<'TXT'
User-agent: Googlebot
This is not a valid line
Disallow: /admin/
TXT;

        $result = $this->parser->parse($text);

        static::assertCount(1, $result->getUserAgentBlocks());
        $block = $result->getUserAgentBlocks()[0];
        static::assertCount(1, $block->directives);
    }

    public function testParseAllKnownDirectiveTypes(): void
    {
        $text = <<<'TXT'
User-agent: *
Disallow: /admin/
Allow: /public/
Crawl-delay: 10
Sitemap: https://example.com/sitemap.xml
Request-rate: 1/10
Visit-time: 0900-1700
Host: example.com
TXT;

        $result = $this->parser->parse($text);

        static::assertCount(1, $result->getUserAgentBlocks());
        $block = $result->getUserAgentBlocks()[0];
        static::assertCount(7, $block->directives);

        $types = array_map(static fn (RobotsDirective $d) => $d->type, $block->directives);
        static::assertContains(RobotsDirectiveType::DISALLOW, $types);
        static::assertContains(RobotsDirectiveType::ALLOW, $types);
        static::assertContains(RobotsDirectiveType::CRAWL_DELAY, $types);
        static::assertContains(RobotsDirectiveType::SITEMAP, $types);
        static::assertContains(RobotsDirectiveType::REQUEST_RATE, $types);
        static::assertContains(RobotsDirectiveType::VISIT_TIME, $types);
        static::assertContains(RobotsDirectiveType::HOST, $types);
    }

    public function testParseCaseInsensitive(): void
    {
        $text = <<<'TXT'
user-agent: googlebot
DISALLOW: /admin/
crawl-delay: 10
TXT;

        $result = $this->parser->parse($text);

        static::assertCount(1, $result->getUserAgentBlocks());
        $block = $result->getUserAgentBlocks()[0];
        static::assertSame('googlebot', $block->userAgent);
        static::assertCount(2, $block->directives);
    }

    public function testParseTrimsWhitespace(): void
    {
        $text = <<<'TXT'
  User-agent:   Googlebot
  Disallow:  /admin/
TXT;

        $result = $this->parser->parse($text);

        static::assertCount(1, $result->getUserAgentBlocks());
        $block = $result->getUserAgentBlocks()[0];
        static::assertSame('Googlebot', $block->userAgent);
        static::assertSame('/admin/', $block->directives[0]->value);
    }

    public function testParseOrphanedNonPathDirectivesAreIgnored(): void
    {
        $text = <<<'TXT'
Crawl-delay: 10
Disallow: /admin/
Sitemap: https://example.com/sitemap.xml
TXT;

        $result = $this->parser->parse($text);

        // Only path directives should be in orphaned
        static::assertCount(1, $result->getOrphanedPathDirectives());
        static::assertSame(RobotsDirectiveType::DISALLOW, $result->getOrphanedPathDirectives()[0]->type);
    }
}
