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
        $xmlFile = \dirname(__FILE__, 3) . '/_fixtures/flow-1-0.xml';

        $this->expectExceptionObject(AppException::createFromXmlFileFlowError(
            $xmlFile,
            \sprintf('Resource "%s" is not a file.', $xmlFile)
        ));

        Event::createFromXmlFile($xmlFile);
    }

    #[DataProvider('invalidFlowEventProvider')]
    public function testCreateFromXmlFailsForInvalidFlowEvent(string $fixture, string $message): void
    {
        $file = \dirname(__FILE__, 3) . '/_fixtures/Resources/' . $fixture;

        $this->expectExceptionObject(AppException::createFromXmlFileFlowError($file, $message));

        Event::createFromXmlFile($file);
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
            'message' => '[ERROR 1871] Element \'flow-event\': Missing child element(s). Expected is ( aware ).',
        ];
    }
}
