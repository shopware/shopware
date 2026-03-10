<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Page\Robots\Event\RobotsDirectiveParsingEvent;
use Shopware\Storefront\Page\Robots\Event\RobotsUnknownDirectiveEvent;
use Shopware\Storefront\Page\Robots\Parser\ParsedRobots;
use Shopware\Storefront\Page\Robots\Parser\ParseIssue;
use Shopware\Storefront\Page\Robots\Parser\ParseIssueSeverity;
use Shopware\Storefront\Page\Robots\Parser\RobotsDirectiveParser;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirectiveType;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(RobotsDirectiveParser::class)]
class RobotsDirectiveParserTest extends TestCase
{
    private RobotsDirectiveParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RobotsDirectiveParser(new EventDispatcher());
    }

    public function testParseEmptyString(): void
    {
        $result = $this->parser->parse('', Context::createDefaultContext());

        static::assertCount(0, $result->userAgentBlocks);
        static::assertCount(0, $result->orphanedPathDirectives);
        static::assertFalse($result->hasUserAgentBlocks());
        static::assertCount(0, $result->issues);
    }

    public function testParseUserAgentBlockWithDirectives(): void
    {
        $text = <<<'TXT'
User-agent: Googlebot
Crawl-delay: 10
Disallow: /admin/
TXT;

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertTrue($result->hasUserAgentBlocks());
        static::assertCount(1, $result->userAgentBlocks);

        $block = $result->userAgentBlocks[0];
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

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertCount(2, $result->userAgentBlocks);

        $block1 = $result->userAgentBlocks[0];
        static::assertSame('Googlebot', $block1->userAgent);
        static::assertCount(2, $block1->directives);

        $block2 = $result->userAgentBlocks[1];
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

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertCount(2, $result->userAgentBlocks);

        // Both user agents should have the same directive
        static::assertSame('Googlebot', $result->userAgentBlocks[0]->userAgent);
        static::assertSame('Bingbot', $result->userAgentBlocks[1]->userAgent);
        static::assertCount(1, $result->userAgentBlocks[0]->directives);
        static::assertCount(1, $result->userAgentBlocks[1]->directives);

        // Verify both blocks have the same directive type and value
        $directive1 = $result->userAgentBlocks[0]->directives[0];
        $directive2 = $result->userAgentBlocks[1]->directives[0];
        static::assertSame(RobotsDirectiveType::DISALLOW, $directive1->type);
        static::assertSame(RobotsDirectiveType::DISALLOW, $directive2->type);
        static::assertSame('/admin/', $directive1->value);
        static::assertSame('/admin/', $directive2->value);
    }

    public function testParseOrphanedPathDirectives(): void
    {
        $text = <<<'TXT'
Disallow: /admin/
Allow: /public/
TXT;

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertFalse($result->hasUserAgentBlocks());
        static::assertCount(2, $result->orphanedPathDirectives);

        $types = array_map(static fn (RobotsDirective $d) => $d->type, $result->orphanedPathDirectives);
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

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertCount(1, $result->userAgentBlocks);
        $block = $result->userAgentBlocks[0];
        static::assertCount(1, $block->directives);
    }

    public function testParseIgnoresEmptyLines(): void
    {
        $text = <<<'TXT'

User-agent: Googlebot

Disallow: /admin/

TXT;

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertCount(1, $result->userAgentBlocks);
        $block = $result->userAgentBlocks[0];
        static::assertCount(1, $block->directives);
    }

    public function testParseIgnoresUnknownDirectives(): void
    {
        $text = <<<'TXT'
User-agent: Googlebot
Unknown-directive: value
Disallow: /admin/
TXT;

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertCount(1, $result->userAgentBlocks);
        $block = $result->userAgentBlocks[0];
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

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertCount(1, $result->userAgentBlocks);
        $block = $result->userAgentBlocks[0];
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

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertCount(1, $result->userAgentBlocks);
        $block = $result->userAgentBlocks[0];
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

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertCount(1, $result->userAgentBlocks);
        $block = $result->userAgentBlocks[0];
        static::assertSame('googlebot', $block->userAgent);
        static::assertCount(2, $block->directives);

        // Verify directive types are normalized to enum constants
        $types = array_map(static fn (RobotsDirective $d) => $d->type, $block->directives);
        static::assertContains(RobotsDirectiveType::DISALLOW, $types);
        static::assertContains(RobotsDirectiveType::CRAWL_DELAY, $types);
    }

    public function testParseTrimsWhitespace(): void
    {
        $text = <<<'TXT'
  User-agent:   Googlebot
  Disallow:  /admin/
TXT;

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertCount(1, $result->userAgentBlocks);
        $block = $result->userAgentBlocks[0];
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

        $result = $this->parser->parse($text, Context::createDefaultContext());

        // Only path directives should be in orphaned
        static::assertCount(1, $result->orphanedPathDirectives);
        static::assertSame(RobotsDirectiveType::DISALLOW, $result->orphanedPathDirectives[0]->type);
    }

    public function testParseOrphanedNonPathDirectiveWarning(): void
    {
        $text = <<<'TXT'
Crawl-delay: 10
Disallow: /admin/
TXT;

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertCount(1, $result->orphanedPathDirectives);
        static::assertCount(1, $result->issues);
        static::assertTrue($result->hasWarnings());

        $warning = $result->issues[0];
        static::assertSame(1, $warning->lineNumber);
        static::assertSame('Crawl-delay: 10', $warning->lineContent);
        static::assertSame('Directive \'Crawl-delay\' found outside user-agent block and will be ignored', $warning->reason);
        static::assertSame(ParseIssueSeverity::WARNING, $warning->severity);
    }

    public function testParseMultipleIssuesWithCorrectLineNumbers(): void
    {
        $text = <<<'TXT'
User-agent: Googlebot
Invalid line without colon
Unknown-Directive: value
Disallow: /admin/
Another invalid line
TXT;

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertCount(1, $result->userAgentBlocks);
        static::assertCount(3, $result->issues);

        static::assertSame(2, $result->issues[0]->lineNumber);
        static::assertSame(ParseIssueSeverity::ERROR, $result->issues[0]->severity);

        static::assertSame(3, $result->issues[1]->lineNumber);
        static::assertSame(ParseIssueSeverity::WARNING, $result->issues[1]->severity);

        static::assertSame(5, $result->issues[2]->lineNumber);
        static::assertSame(ParseIssueSeverity::ERROR, $result->issues[2]->severity);
    }

    public function testParseMixedErrorsAndWarnings(): void
    {
        $text = <<<'TXT'
Crawl-delay: 10
Invalid line
User-agent: Googlebot
Unknown-Directive: value
Disallow: /admin/
TXT;

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertCount(3, $result->issues);
        static::assertTrue($result->hasErrors());
        static::assertTrue($result->hasWarnings());
        static::assertCount(1, $result->getErrors());
        static::assertCount(2, $result->getWarnings());

        // Line 1: Orphaned non-path directive warning
        static::assertSame(1, $result->issues[0]->lineNumber);
        static::assertSame(ParseIssueSeverity::WARNING, $result->issues[0]->severity);

        // Line 2: Malformed line error
        static::assertSame(2, $result->issues[1]->lineNumber);
        static::assertSame(ParseIssueSeverity::ERROR, $result->issues[1]->severity);

        // Line 4: Unknown directive warning
        static::assertSame(4, $result->issues[2]->lineNumber);
        static::assertSame(ParseIssueSeverity::WARNING, $result->issues[2]->severity);
    }

    public function testParseLineNumbersWithEmptyLinesAndComments(): void
    {
        $text = <<<'TXT'
# Comment line 1

User-agent: Googlebot
# Comment line 4
Invalid line without colon
Disallow: /admin/
TXT;

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertCount(1, $result->issues);
        static::assertSame(5, $result->issues[0]->lineNumber);
        static::assertSame('Invalid line without colon', $result->issues[0]->lineContent);
    }

    public function testParseNoIssuesForValidInput(): void
    {
        $text = <<<'TXT'
User-agent: Googlebot
Crawl-delay: 10
Disallow: /admin/
Allow: /public/
TXT;

        $result = $this->parser->parse($text, Context::createDefaultContext());

        static::assertCount(0, $result->issues);
        static::assertFalse($result->hasErrors());
        static::assertFalse($result->hasWarnings());
    }

    public function testDispatchesParsingEvent(): void
    {
        $eventDispatched = false;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            RobotsDirectiveParsingEvent::class,
            function () use (&$eventDispatched): void {
                $eventDispatched = true;
            }
        );

        $parser = new RobotsDirectiveParser($eventDispatcher);
        $parser->parse('User-agent: *\nDisallow: /', Context::createDefaultContext());

        static::assertTrue($eventDispatched, 'RobotsDirectiveParsingEvent should be dispatched');
    }

    public function testDispatchesUnknownDirectiveEvent(): void
    {
        $eventDispatched = false;
        $unknownDirectiveType = null;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            RobotsUnknownDirectiveEvent::class,
            function (RobotsUnknownDirectiveEvent $event) use (&$eventDispatched, &$unknownDirectiveType): void {
                $eventDispatched = true;
                $unknownDirectiveType = $event->directiveType;
            }
        );

        $parser = new RobotsDirectiveParser($eventDispatcher);
        $result = $parser->parse('Unknown-Directive: test', Context::createDefaultContext());

        static::assertTrue($eventDispatched, 'RobotsUnknownDirectiveEvent should be dispatched');
        static::assertSame('Unknown-Directive', $unknownDirectiveType);
        static::assertCount(1, $result->issues); // Should still have warning
    }

    public function testUnknownDirectiveEventCanPreventWarning(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            RobotsUnknownDirectiveEvent::class,
            function (RobotsUnknownDirectiveEvent $event): void {
                if ($event->directiveType === 'Clean-param') {
                    $event->handled = true; // Mark as handled to prevent warning
                }
            }
        );

        $parser = new RobotsDirectiveParser($eventDispatcher);
        $result = $parser->parse('Clean-param: test', Context::createDefaultContext());

        static::assertCount(0, $result->issues, 'Handled directive should not generate warning');
    }

    public function testUnknownDirectiveEventCanSetCustomIssue(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            RobotsUnknownDirectiveEvent::class,
            function (RobotsUnknownDirectiveEvent $event): void {
                if ($event->directiveType === 'Test-Directive') {
                    $customIssue = new ParseIssue(
                        $event->lineNumber,
                        $event->line,
                        'This is a custom error message',
                        ParseIssueSeverity::ERROR
                    );
                    $event->issue = $customIssue;
                }
            }
        );

        $parser = new RobotsDirectiveParser($eventDispatcher);
        $result = $parser->parse('Test-Directive: value', Context::createDefaultContext());

        static::assertCount(1, $result->issues);
        static::assertSame('This is a custom error message', $result->issues[0]->reason);
        static::assertSame(ParseIssueSeverity::ERROR, $result->issues[0]->severity);
    }

    public function testParsingEventCanModifyResult(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            RobotsDirectiveParsingEvent::class,
            function (RobotsDirectiveParsingEvent $event): void {
                // Add a custom issue via the event
                $parsedResult = $event->parsedResult;
                $newIssues = array_values([
                    ...$parsedResult->issues,
                    new ParseIssue(
                        0,
                        '',
                        'Custom validation failed',
                        ParseIssueSeverity::WARNING
                    ),
                ]);
                $modifiedResult = new ParsedRobots(
                    $parsedResult->userAgentBlocks,
                    $parsedResult->orphanedPathDirectives,
                    $newIssues
                );
                $event->parsedResult = $modifiedResult;
            }
        );

        $parser = new RobotsDirectiveParser($eventDispatcher);
        $result = $parser->parse('User-agent: *\nDisallow: /', Context::createDefaultContext());

        static::assertCount(1, $result->issues);
        static::assertSame('Custom validation failed', $result->issues[0]->reason);
    }
}
