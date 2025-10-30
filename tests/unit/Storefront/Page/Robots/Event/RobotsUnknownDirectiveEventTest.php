<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Page\Robots\Event\RobotsUnknownDirectiveEvent;
use Shopware\Storefront\Page\Robots\Parser\ParseIssue;
use Shopware\Storefront\Page\Robots\Parser\ParseIssueSeverity;

/**
 * @internal
 */
#[CoversClass(RobotsUnknownDirectiveEvent::class)]
class RobotsUnknownDirectiveEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $lineNumber = 5;
        $line = 'Clean-param: test';
        $directiveType = 'Clean-param';
        $directiveValue = 'test';
        $insideUserAgentBlock = true;
        $context = Context::createDefaultContext();
        $salesChannelId = 'test-channel-id';

        $event = new RobotsUnknownDirectiveEvent(
            $lineNumber,
            $line,
            $directiveType,
            $directiveValue,
            $insideUserAgentBlock,
            $context,
            $salesChannelId
        );

        static::assertSame($lineNumber, $event->lineNumber);
        static::assertSame($line, $event->line);
        static::assertSame($directiveType, $event->directiveType);
        static::assertSame($directiveValue, $event->directiveValue);
        static::assertTrue($event->insideUserAgentBlock);
        static::assertSame($context, $event->getContext());
        static::assertSame($salesChannelId, $event->salesChannelId);
    }

    public function testHandledDefaultsToFalse(): void
    {
        $event = new RobotsUnknownDirectiveEvent(
            1,
            'test: value',
            'test',
            'value',
            false,
            Context::createDefaultContext()
        );

        static::assertFalse($event->handled);
    }

    public function testSetHandledUpdatesValue(): void
    {
        $event = new RobotsUnknownDirectiveEvent(
            1,
            'test: value',
            'test',
            'value',
            false,
            Context::createDefaultContext()
        );

        $event->handled = true;
        static::assertTrue($event->handled);

        $event->handled = false;
        static::assertFalse($event->handled);
    }

    public function testIssueDefaultsToNull(): void
    {
        $event = new RobotsUnknownDirectiveEvent(
            1,
            'test: value',
            'test',
            'value',
            false,
            Context::createDefaultContext()
        );

        static::assertNull($event->issue);
    }

    public function testSetIssueUpdatesValue(): void
    {
        $event = new RobotsUnknownDirectiveEvent(
            1,
            'test: value',
            'test',
            'value',
            false,
            Context::createDefaultContext()
        );

        $issue = new ParseIssue(1, 'test: value', 'Custom error', ParseIssueSeverity::ERROR);
        $event->issue = $issue;

        static::assertSame($issue, $event->issue);
    }

    public function testSetIssueCanSetNull(): void
    {
        $event = new RobotsUnknownDirectiveEvent(
            1,
            'test: value',
            'test',
            'value',
            false,
            Context::createDefaultContext()
        );

        $issue = new ParseIssue(1, 'test: value', 'Custom error', ParseIssueSeverity::ERROR);
        $event->issue = $issue;
        static::assertSame($issue, $event->issue);

        $event->issue = null;
        static::assertNull($event->issue);
    }

    public function testSalesChannelIdCanBeNull(): void
    {
        $event = new RobotsUnknownDirectiveEvent(
            1,
            'test: value',
            'test',
            'value',
            false,
            Context::createDefaultContext(),
            null
        );

        static::assertNull($event->salesChannelId);
    }
}
