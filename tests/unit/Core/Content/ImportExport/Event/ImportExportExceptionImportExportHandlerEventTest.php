<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportExportHandlerEvent;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Message\ImportExportMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ImportExportExceptionImportExportHandlerEvent::class)]
class ImportExportExceptionImportExportHandlerEventTest extends TestCase
{
    public function testConstructorRequiresContextWhenFeatureActive(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');

        $this->expectException(FeatureException::class);
        new ImportExportExceptionImportExportHandlerEvent(new \RuntimeException('test'), $message);
    }

    public function testClearExceptionRemovesException(): void
    {
        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $event = new ImportExportExceptionImportExportHandlerEvent(
            new \RuntimeException('test'),
            $message,
            Context::createDefaultContext()
        );

        $event->clearException();

        static::assertFalse($event->hasException());
        static::assertNull($event->getException());
    }

    public function testSetExceptionReplacesException(): void
    {
        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $event = new ImportExportExceptionImportExportHandlerEvent(
            new \RuntimeException('test'),
            $message,
            Context::createDefaultContext()
        );

        $newException = new \LogicException('new');
        $event->setException($newException);

        static::assertTrue($event->hasException());
        static::assertSame($newException, $event->getException());
    }

    public function testSetExceptionWithNullClearsException(): void
    {
        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $event = new ImportExportExceptionImportExportHandlerEvent(
            new \RuntimeException('test'),
            $message,
            Context::createDefaultContext()
        );

        $event->setException(null);

        static::assertFalse($event->hasException());
        static::assertNull($event->getException());
    }

    public function testGetContextThrowsWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $event = new ImportExportExceptionImportExportHandlerEvent(new \RuntimeException('test'), $message);

        $this->expectExceptionObject(ImportExportException::invalidEventData('No context provided. Pass $context to the constructor of ' . ImportExportExceptionImportExportHandlerEvent::class));
        $event->getContext();
    }

    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $event = new ImportExportExceptionImportExportHandlerEvent(new \RuntimeException('test'), $message);

        static::assertNull($event->getNullableContext());
    }
}
