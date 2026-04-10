<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;

/**
 * @internal
 */
#[CoversClass(ImportExportBeforeExportRecordEvent::class)]
class ImportExportBeforeExportRecordEventTest extends TestCase
{
    public function testConstructorRequiresContextWhenFeatureActive(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $this->expectException(FeatureException::class);
        new ImportExportBeforeExportRecordEvent(
            new Config([], [], []),
            ['key' => 'value'],
            ['key' => 'original']
        );
    }

    public function testSetRecordDoesNotMutateOriginalRecord(): void
    {
        $originalRecord = ['key' => 'original'];
        $event = new ImportExportBeforeExportRecordEvent(
            new Config([], [], []),
            ['key' => 'value'],
            $originalRecord,
            Context::createDefaultContext()
        );

        $event->setRecord(['key' => 'new']);

        static::assertSame($originalRecord, $event->getOriginalRecord());
    }

    public function testGetContextThrowsWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new ImportExportBeforeExportRecordEvent(
            new Config([], [], []),
            ['key' => 'value'],
            ['key' => 'original']
        );

        $this->expectExceptionObject(ImportExportException::invalidEventData('No context provided. Pass $context to the constructor of ' . ImportExportBeforeExportRecordEvent::class));
        $event->getContext();
    }

    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new ImportExportBeforeExportRecordEvent(
            new Config([], [], []),
            ['key' => 'value'],
            ['key' => 'original']
        );

        static::assertNull($event->getNullableContext());
    }
}
