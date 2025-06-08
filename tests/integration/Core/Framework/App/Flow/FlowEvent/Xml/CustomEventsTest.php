<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Flow\FlowEvent\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Event\Event;

/**
 * @internal
 */
class CustomEventsTest extends TestCase
{
    public function testFromXml(): void
    {
        $flowEvents = Event::createFromXmlFile(__DIR__ . '/../_fixtures/valid/flowEventWithFlowEvents.xml');
        static::assertNotNull($flowEvents->getCustomEvents());
        static::assertCount(1, $flowEvents->getCustomEvents()->getCustomEvents());
    }
}
