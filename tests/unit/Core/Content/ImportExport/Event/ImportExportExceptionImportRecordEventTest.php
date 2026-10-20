<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportRecordEvent;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ImportExportExceptionImportRecordEvent::class)]
class ImportExportExceptionImportRecordEventTest extends TestCase
{
    public function testExposesItsPayload(): void
    {
        $config = new Config([], [], []);
        $context = Context::createDefaultContext();

        $event = new ImportExportExceptionImportRecordEvent(
            new \RuntimeException('import failed'),
            ['key' => 'record'],
            ['key' => 'row'],
            $config,
            $context
        );

        static::assertSame(['key' => 'record'], $event->getRecord());
        static::assertSame(['key' => 'row'], $event->getRow());
        static::assertSame($config, $event->getConfig());
        static::assertSame($context, $event->getContext());
    }

    public function testExceptionCanBeRemoved(): void
    {
        $exception = new \RuntimeException('import failed');
        $event = new ImportExportExceptionImportRecordEvent(
            $exception,
            [],
            [],
            new Config([], [], []),
            Context::createDefaultContext()
        );

        static::assertTrue($event->hasException());
        static::assertSame($exception, $event->getException());

        $event->removeException();

        static::assertFalse($event->hasException());
        static::assertNull($event->getException());
    }
}
