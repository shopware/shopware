<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Flow\FlowEvent;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Flow\Event\Event;

/**
 * @internal
 */
class FlowEventTest extends TestCase
{
    public function testCreateFromXmlWithFlowEvent(): void
    {
        $flowEventsFile = '/_fixtures/valid/flowEventWithFlowEvents.xml';
        $flowEvents = Event::createFromXmlFile(__DIR__ . $flowEventsFile);

        static::assertSame(__DIR__ . '/_fixtures/valid', $flowEvents->getPath());
        static::assertNotNull($flowEvents->getCustomEvents());
        static::assertCount(1, $flowEvents->getCustomEvents()->getCustomEvents());
    }

    public function testCreateFromXmlMissingFlowEvent(): void
    {
        $this->expectException(AppException::class);

        $this->expectExceptionMessage('[ERROR 1871] Element \'flow-events\': Missing child element(s). Expected is ( flow-event ).');

        $flowEventsFile = '/_fixtures/invalid/flowEventWithoutFlowEvents.xml';
        Event::createFromXmlFile(__DIR__ . $flowEventsFile);
    }

    public function testCreateFromXmlFlowEventMissingRequiredChild(): void
    {
        $this->expectException(AppException::class);

        $this->expectExceptionMessage('[ERROR 1871] Element \'flow-event\': Missing child element(s). Expected is ( name ).');

        $flowEventsFile = '/_fixtures/invalid/flowEventWithoutRequiredChild.xml';
        Event::createFromXmlFile(__DIR__ . $flowEventsFile);
    }

    public function testCreateFromXmlFlowEventMetaMissingRequiredChild(): void
    {
        $this->expectException(AppException::class);

        $this->expectExceptionMessage('Message: [ERROR 1871] Element \'flow-event\': Missing child element(s). Expected is ( aware ).');

        $flowEventsFile = '/_fixtures/invalid/flowEventMetaWithoutRequiredChild.xml';
        Event::createFromXmlFile(__DIR__ . $flowEventsFile);
    }
}
