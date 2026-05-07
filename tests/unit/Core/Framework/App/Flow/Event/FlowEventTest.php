<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Flow\Event\Event;

/**
 * @internal
 */
#[CoversClass(Event::class)]
class FlowEventTest extends TestCase
{
    public function testCreateFromXmlFile(): void
    {
        $xmlFile = \dirname(__FILE__, 3) . '/_fixtures/Resources/flow-event-with-events.xml';
        $result = Event::createFromXmlFile($xmlFile);

        static::assertSame(\dirname($xmlFile), $result->getPath());
        static::assertNotNull($result->getCustomEvents());
        static::assertCount(1, $result->getCustomEvents()->getCustomEvents());
    }

    public function testCreateFromXmlFileFailed(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessageMatches('/Unable to parse file \".*flow-1-0.xml"\. Message: Resource \".*flow-1-0.xml\" is not a file./');

        $xmlFile = \dirname(__FILE__, 3) . '/_fixtures/flow-1-0.xml';
        Event::createFromXmlFile($xmlFile);
    }

    #[DataProvider('invalidFlowEventProvider')]
    public function testCreateFromXmlFailsForInvalidFlowEvent(string $fixture, string $message): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage($message);

        Event::createFromXmlFile(\dirname(__FILE__, 3) . '/_fixtures/Resources/' . $fixture);
    }

    /**
     * @return iterable<string, array{fixture: string, message: string}>
     */
    public static function invalidFlowEventProvider(): iterable
    {
        yield 'missing flow-event' => [
            'fixture' => 'flow-event-without-events.xml',
            'message' => '[ERROR 1871] Element \'flow-events\': Missing child element(s). Expected is ( flow-event ).',
        ];

        yield 'missing event child' => [
            'fixture' => 'flow-event-without-required-child.xml',
            'message' => '[ERROR 1871] Element \'flow-event\': Missing child element(s). Expected is ( name ).',
        ];

        yield 'missing aware child' => [
            'fixture' => 'flow-event-without-aware.xml',
            'message' => 'Message: [ERROR 1871] Element \'flow-event\': Missing child element(s). Expected is ( aware ).',
        ];
    }
}
