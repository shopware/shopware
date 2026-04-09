<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ImportExportBeforeExportRecordEvent::class)]
class ImportExportBeforeExportRecordEventTest extends TestCase
{
    public function testGetters(): void
    {
        $config = new Config([], [], []);
        $record = ['key' => 'value'];
        $originalRecord = ['key' => 'original'];
        $context = Context::createDefaultContext();

        $event = new ImportExportBeforeExportRecordEvent($config, $record, $originalRecord, $context);

        static::assertSame($config, $event->getConfig());
        static::assertSame($record, $event->getRecord());
        static::assertSame($originalRecord, $event->getOriginalRecord());
        static::assertSame($context, $event->getContext());
    }

    public function testSetRecord(): void
    {
        $event = new ImportExportBeforeExportRecordEvent(
            new Config([], [], []),
            ['key' => 'value'],
            ['key' => 'original'],
            Context::createDefaultContext()
        );

        $newRecord = ['key' => 'new'];
        $event->setRecord($newRecord);

        static::assertSame($newRecord, $event->getRecord());
    }

    public function testGetContextThrowsWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = @new ImportExportBeforeExportRecordEvent(
            new Config([], [], []),
            ['key' => 'value'],
            ['key' => 'original']
        );

        $this->expectException(ImportExportException::class);
        $event->getContext();
    }

    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = @new ImportExportBeforeExportRecordEvent(
            new Config([], [], []),
            ['key' => 'value'],
            ['key' => 'original']
        );

        static::assertNull(@$event->getNullableContext());
    }
}
