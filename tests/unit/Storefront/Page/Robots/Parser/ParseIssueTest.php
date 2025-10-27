<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Page\Robots\Parser\ParseIssue;
use Shopware\Storefront\Page\Robots\Parser\ParseIssueSeverity;

/**
 * @internal
 */
#[CoversClass(ParseIssue::class)]
class ParseIssueTest extends TestCase
{
    public function testCreateWithError(): void
    {
        $issue = new ParseIssue(
            42,
            'Invalid: line',
            'Test reason',
            ParseIssueSeverity::ERROR
        );

        static::assertSame(42, $issue->lineNumber);
        static::assertSame('Invalid: line', $issue->lineContent);
        static::assertSame('Test reason', $issue->reason);
        static::assertSame(ParseIssueSeverity::ERROR, $issue->severity);
    }

    public function testCreateWithWarning(): void
    {
        $issue = new ParseIssue(
            10,
            'Unknown-Directive: value',
            'Unknown directive type',
            ParseIssueSeverity::WARNING
        );

        static::assertSame(10, $issue->lineNumber);
        static::assertSame('Unknown-Directive: value', $issue->lineContent);
        static::assertSame('Unknown directive type', $issue->reason);
        static::assertSame(ParseIssueSeverity::WARNING, $issue->severity);
    }

    public function testPropertiesAreReadonly(): void
    {
        $issue = new ParseIssue(1, 'test', 'reason', ParseIssueSeverity::ERROR);

        $reflection = new \ReflectionClass($issue);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            static::assertTrue($property->isReadOnly(), 'Property ' . $property->getName() . ' should be readonly');
        }
    }
}
