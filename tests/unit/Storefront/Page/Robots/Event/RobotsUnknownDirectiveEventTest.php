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

        static::assertSame($lineNumber, $event->getLineNumber());
        static::assertSame($line, $event->getLine());
        static::assertSame($directiveType, $event->getDirectiveType());
        static::assertSame($directiveValue, $event->getDirectiveValue());
        static::assertTrue($event->isInsideUserAgentBlock());
        static::assertSame($context, $event->getContext());
        static::assertSame($salesChannelId, $event->getSalesChannelId());
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

        static::assertFalse($event->isHandled());
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

        $event->setHandled(true);
        static::assertTrue($event->isHandled());

        $event->setHandled(false);
        static::assertFalse($event->isHandled());
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

        static::assertNull($event->getIssue());
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
        $event->setIssue($issue);

        static::assertSame($issue, $event->getIssue());
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
        $event->setIssue($issue);
        static::assertSame($issue, $event->getIssue());

        $event->setIssue(null);
        static::assertNull($event->getIssue());
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

        static::assertNull($event->getSalesChannelId());
    }
}
