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
    public function testHasUserAgentBlocksLogic(): void
    {
        $emptyParsed = new ParsedRobots([], []);
        static::assertFalse($emptyParsed->hasUserAgentBlocks());

        $withBlocksParsed = new ParsedRobots([
            new RobotsUserAgentBlock('Googlebot', [
                new RobotsDirective(RobotsDirectiveType::CRAWL_DELAY, '10'),
            ]),
        ], []);
        static::assertTrue($withBlocksParsed->hasUserAgentBlocks());
    }

    public function testIssueFilteringByError(): void
    {
        $issues = [
            new ParseIssue(1, 'Invalid line', 'Malformed line', ParseIssueSeverity::ERROR),
            new ParseIssue(2, 'Unknown: directive', 'Unknown directive type', ParseIssueSeverity::WARNING),
            new ParseIssue(3, 'Bad line', 'Another error', ParseIssueSeverity::ERROR),
            new ParseIssue(4, 'Orphaned: directive', 'Orphaned directive', ParseIssueSeverity::WARNING),
        ];

        $parsed = new ParsedRobots([], [], $issues);

        static::assertTrue($parsed->hasErrors());
        static::assertTrue($parsed->hasWarnings());

        $errors = $parsed->getErrors();
        static::assertCount(2, $errors);
        static::assertArrayHasKey(0, $errors);
        static::assertArrayHasKey(1, $errors);
        static::assertSame(ParseIssueSeverity::ERROR, $errors[0]->severity);
        static::assertSame(ParseIssueSeverity::ERROR, $errors[1]->severity);

        $warnings = $parsed->getWarnings();
        static::assertCount(2, $warnings);
        static::assertArrayHasKey(0, $warnings);
        static::assertArrayHasKey(1, $warnings);
        static::assertSame(ParseIssueSeverity::WARNING, $warnings[0]->severity);
        static::assertSame(ParseIssueSeverity::WARNING, $warnings[1]->severity);
    }

    public function testGetErrorsReturnsListNotAssociativeArray(): void
    {
        $issues = [
            new ParseIssue(1, 'Unknown: directive', 'Unknown directive type', ParseIssueSeverity::WARNING),
            new ParseIssue(2, 'Invalid line', 'Malformed line', ParseIssueSeverity::ERROR),
            new ParseIssue(3, 'Another: warning', 'Another warning', ParseIssueSeverity::WARNING),
        ];

        $parsed = new ParsedRobots([], [], $issues);

        $errors = $parsed->getErrors();
        static::assertCount(1, $errors);
        static::assertArrayHasKey(0, $errors);
        static::assertSame('Malformed line', $errors[0]->reason);
    }

    public function testGetWarningsReturnsListNotAssociativeArray(): void
    {
        $issues = [
            new ParseIssue(1, 'Invalid line', 'Malformed line', ParseIssueSeverity::ERROR),
            new ParseIssue(2, 'Unknown: directive', 'Unknown directive type', ParseIssueSeverity::WARNING),
            new ParseIssue(3, 'Bad line', 'Another error', ParseIssueSeverity::ERROR),
        ];

        $parsed = new ParsedRobots([], [], $issues);

        $warnings = $parsed->getWarnings();
        static::assertCount(1, $warnings);
        static::assertArrayHasKey(0, $warnings);
        static::assertSame('Unknown directive type', $warnings[0]->reason);
    }
}
