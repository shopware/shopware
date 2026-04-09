<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportExportHandlerEvent;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Message\ImportExportMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ImportExportExceptionImportExportHandlerEvent::class)]
class ImportExportExceptionImportExportHandlerEventTest extends TestCase
{
    public function testGetters(): void
    {
        $exception = new \RuntimeException('test');
        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $context = Context::createDefaultContext();

        $event = new ImportExportExceptionImportExportHandlerEvent($exception, $message, $context);

        static::assertSame($exception, $event->getException());
        static::assertSame($message, $event->getMessage());
        static::assertSame($context, $event->getContext());
        static::assertTrue($event->hasException());
    }

    public function testSetAndClearException(): void
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

        $newException = new \LogicException('new');
        $event->setException($newException);
        static::assertTrue($event->hasException());
        static::assertSame($newException, $event->getException());
    }

    public function testGetContextThrowsWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $event = @new ImportExportExceptionImportExportHandlerEvent(new \RuntimeException('test'), $message);

        $this->expectException(ImportExportException::class);
        $event->getContext();
    }

    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $message = new ImportExportMessage(Context::createDefaultContext(), Uuid::randomHex(), 'import');
        $event = @new ImportExportExceptionImportExportHandlerEvent(new \RuntimeException('test'), $message);

        static::assertNull(@$event->getNullableContext());
    }
}
