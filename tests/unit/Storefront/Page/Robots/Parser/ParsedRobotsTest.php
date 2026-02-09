<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Page\Robots\Parser\ParsedRobots;
use Shopware\Storefront\Page\Robots\Parser\ParseIssue;
use Shopware\Storefront\Page\Robots\Parser\ParseIssueSeverity;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirectiveType;
use Shopware\Storefront\Page\Robots\Struct\RobotsUserAgentBlock;

/**
 * @internal
 */
#[CoversClass(ParsedRobots::class)]
class ParsedRobotsTest extends TestCase
{
    public function testHasUserAgentBlocks(): void
    {
        // Empty parsed result has no user agent blocks
        $emptyParsed = new ParsedRobots([], [], []);
        static::assertFalse($emptyParsed->hasUserAgentBlocks());

        // Parsed result with user agent blocks
        $userAgentBlocks = [
            new RobotsUserAgentBlock('Googlebot', [
                new RobotsDirective(RobotsDirectiveType::DISALLOW, '/admin/'),
            ]),
        ];
        $parsedWithBlocks = new ParsedRobots($userAgentBlocks, [], []);
        static::assertTrue($parsedWithBlocks->hasUserAgentBlocks());
    }

    public function testIssueFiltering(): void
    {
        // Test with no issues
        $emptyParsed = new ParsedRobots([], [], []);
        static::assertFalse($emptyParsed->hasErrors());
        static::assertFalse($emptyParsed->hasWarnings());
        static::assertCount(0, $emptyParsed->getErrors());
        static::assertCount(0, $emptyParsed->getWarnings());

        // Test with mixed errors and warnings
        $issues = [
            new ParseIssue(1, 'Invalid line', 'Malformed line', ParseIssueSeverity::ERROR),
            new ParseIssue(2, 'Unknown: directive', 'Unknown directive type', ParseIssueSeverity::WARNING),
            new ParseIssue(3, 'Bad line', 'Another error', ParseIssueSeverity::ERROR),
            new ParseIssue(4, 'Orphaned: directive', 'Orphaned directive', ParseIssueSeverity::WARNING),
        ];

        $parsed = new ParsedRobots([], [], $issues);

        // Test has* methods
        static::assertTrue($parsed->hasErrors());
        static::assertTrue($parsed->hasWarnings());

        // Test get* methods filter correctly
        $errors = $parsed->getErrors();
        $warnings = $parsed->getWarnings();

        static::assertCount(2, $errors);
        static::assertCount(2, $warnings);

        // Verify returned arrays are lists (sequential keys)
        static::assertArrayHasKey(0, $errors);
        static::assertArrayHasKey(1, $errors);
        static::assertArrayHasKey(0, $warnings);
        static::assertArrayHasKey(1, $warnings);

        // Verify correct severity filtering
        static::assertSame(ParseIssueSeverity::ERROR, $errors[0]->severity);
        static::assertSame(ParseIssueSeverity::ERROR, $errors[1]->severity);
        static::assertSame(ParseIssueSeverity::WARNING, $warnings[0]->severity);
        static::assertSame(ParseIssueSeverity::WARNING, $warnings[1]->severity);
    }
}
