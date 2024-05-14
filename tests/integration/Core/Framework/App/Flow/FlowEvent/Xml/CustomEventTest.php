<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Flow\FlowEvent\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Event\Event;

/**
 * @internal
 */
class CustomEventTest extends TestCase
{
    public function testFromXml(): void
    {
        $flowEvents = Event::createFromXmlFile(__DIR__ . '/../_fixtures/valid/flowEventWithFlowEvents.xml');

        static::assertNotNull($flowEvents->getCustomEvents());
        static::assertCount(1, $flowEvents->getCustomEvents()->getCustomEvents());

        $firstEvent = $flowEvents->getCustomEvents()->getCustomEvents()[0];
        static::assertSame('checkout.order.place.custom', $firstEvent->getName());

        static::assertSame('checkout.order.place.custom', $firstEvent->getName());
        static::assertEquals(['orderAware', 'customerAware'], $firstEvent->getAware());
    }
}
