<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Event;

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductExportLoggingEvent::class)]
class ProductExportLoggingEventTest extends TestCase
{
    public function testFallsBackToTheDefaultNameAndDebugLevel(): void
    {
        $event = new ProductExportLoggingEvent(Context::createDefaultContext(), null, null);

        static::assertSame(ProductExportLoggingEvent::NAME, $event->getName());
        static::assertSame(Level::Debug, $event->getLogLevel());
        static::assertSame([], $event->getLogData());
    }

    public function testLogDataContainsTheThrowable(): void
    {
        $throwable = new \RuntimeException('export failed');

        $event = new ProductExportLoggingEvent(Context::createDefaultContext(), 'custom.name', Level::Error, $throwable);

        static::assertSame('custom.name', $event->getName());
        static::assertSame(Level::Error, $event->getLogLevel());
        $exception = $event->getLogData()['exception'] ?? null;
        static::assertIsString($exception);
        static::assertStringContainsString('export failed', $exception);
    }

    public function testExposesItsFlowPayload(): void
    {
        $context = Context::createDefaultContext();
        $event = new ProductExportLoggingEvent($context, 'custom.name', null);

        static::assertSame($context, $event->getContext());
        static::assertSame(['name' => 'custom.name'], $event->getValues());
        static::assertNull($event->getSalesChannelId());
        static::assertSame(['name'], array_keys(ProductExportLoggingEvent::getAvailableData()->toArray()));
    }

    public function testMailStructIsNotAvailable(): void
    {
        $event = new ProductExportLoggingEvent(Context::createDefaultContext(), null, null);

        $this->expectException(MailEventConfigurationException::class);
        $event->getMailStruct();
    }
}
