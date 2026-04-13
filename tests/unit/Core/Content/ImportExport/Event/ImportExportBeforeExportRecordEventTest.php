<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(ImportExportBeforeExportRecordEvent::class)]
class ImportExportBeforeExportRecordEventTest extends TestCase
{
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

    public function testGetContextReturnsPassedContext(): void
    {
        $context = Context::createDefaultContext();
        $event = new ImportExportBeforeExportRecordEvent(
            new Config([], [], []),
            ['key' => 'value'],
            ['key' => 'original'],
            $context
        );

        static::assertSame($context, $event->getContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetNullableContextReturnsContextWhenFeatureInactiveAndContextProvided(): void
    {
        $context = Context::createDefaultContext();
        $event = new ImportExportBeforeExportRecordEvent(
            new Config([], [], []),
            ['key' => 'value'],
            ['key' => 'original'],
            $context
        );

        static::assertSame($context, $event->getNullableContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        $event = new ImportExportBeforeExportRecordEvent(
            new Config([], [], []),
            ['key' => 'value'],
            ['key' => 'original']
        );

        static::assertNull($event->getNullableContext());
    }

    public function testConstructorRequiresContextWhenFeatureActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Not passing $context to ' . ImportExportBeforeExportRecordEvent::class . ' is deprecated and will be required in v6.8.0.'
        ));
        new ImportExportBeforeExportRecordEvent(
            new Config([], [], []),
            ['key' => 'value'],
            ['key' => 'original']
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetContextThrowsWithoutContext(): void
    {
        $event = new ImportExportBeforeExportRecordEvent(
            new Config([], [], []),
            ['key' => 'value'],
            ['key' => 'original']
        );

        $this->expectExceptionObject(ImportExportException::invalidEventData('No context provided. Pass $context to the constructor of ' . ImportExportBeforeExportRecordEvent::class));
        $event->getContext();
    }

    public function testGetNullableContextThrowsWhenFeatureActive(): void
    {
        $event = new ImportExportBeforeExportRecordEvent(
            new Config([], [], []),
            ['key' => 'value'],
            ['key' => 'original'],
            Context::createDefaultContext()
        );

        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: getNullableContext() is deprecated, use getContext() instead.'));
        $event->getNullableContext();
    }
}
