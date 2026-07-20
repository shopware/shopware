<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Page\Robots\Event\RobotsDirectiveParsingEvent;
use Shopware\Storefront\Page\Robots\Parser\ParsedRobots;

/**
 * @internal
 */
#[CoversClass(RobotsDirectiveParsingEvent::class)]
class RobotsDirectiveParsingEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $text = "User-agent: *\nDisallow: /admin/";
        $parsedResult = new ParsedRobots([], []);
        $context = Context::createDefaultContext();
        $salesChannelId = 'test-channel-id';

        $event = new RobotsDirectiveParsingEvent($text, $parsedResult, $context, $salesChannelId);

        static::assertSame($text, $event->text);
        static::assertSame($parsedResult, $event->parsedResult);
        static::assertSame($context, $event->getContext());
        static::assertSame($salesChannelId, $event->salesChannelId);
    }

    public function testSetParsedResultUpdatesResult(): void
    {
        $originalResult = new ParsedRobots([], []);
        $newResult = new ParsedRobots([], [], []);
        $context = Context::createDefaultContext();

        $event = new RobotsDirectiveParsingEvent('test', $originalResult, $context);

        static::assertSame($originalResult, $event->parsedResult);

        $event->parsedResult = $newResult;

        static::assertSame($newResult, $event->parsedResult);
        static::assertNotSame($originalResult, $event->parsedResult);
    }

    public function testSalesChannelIdCanBeNull(): void
    {
        $parsedResult = new ParsedRobots([], []);
        $context = Context::createDefaultContext();

        $event = new RobotsDirectiveParsingEvent('test', $parsedResult, $context, null);

        static::assertNull($event->salesChannelId);
    }
}
